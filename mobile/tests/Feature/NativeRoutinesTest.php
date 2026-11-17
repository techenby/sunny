<?php

use App\Http\Integrations\Sunny\Requests\GetRoutineOccurrencesRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\Requests\UpdateRoutineStepRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\PendingWrite;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

function routineDayResponse(string $date, string $name = 'Get ready'): MockResponse
{
    return MockResponse::make(['data' => [[
        'id' => 4,
        'routine_id' => 1,
        'due_on' => $date,
        'routine' => ['id' => 1, 'team_id' => 1, 'name' => $name, 'time_of_day' => 'morning', 'user' => null],
        'steps' => [
            ['id' => 41, 'name' => 'Make bed', 'position' => 1, 'completed_at' => '2026-09-29T13:00:00.000000Z'],
            ['id' => 42, 'name' => 'Feed the cat', 'position' => 2, 'completed_at' => null],
        ],
    ]]]);
}

function offlineResponse(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $request) => new FatalRequestException(new RuntimeException('Could not resolve host'), $request));
}

function dayBarWithToday(string $type): Closure
{
    return fn (array $node): bool => ($node['ref'] ?? null) === 'day-bar'
        && array_column($node['children'], 'ref') === ['previous-day', 'back-to-today', 'next-day']
        && $node['children'][1]['type'] === $type;
}

function routineStepResponse(int $id, ?string $completedAt): MockResponse
{
    return MockResponse::make(['data' => ['id' => $id, 'name' => 'Feed the cat', 'position' => 2, 'completed_at' => $completedAt, 'completed_by' => 7]]);
}

it('lists today’s routines by time of day with their progress', function (): void {
    $screen = Native::visit('/today')
        ->assertNavTitle('Today')
        ->assertSee(now()->format('l, F j'))
        ->assertSee('Get ready')
        ->assertSee('Bedtime')
        ->assertDontSee('Take out trash')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-subtitle'
            && ($node['props']['text'] ?? null) === 'Morning · 1 of 3')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-step-11'
            && ($node['props']['a11y_label'] ?? null) === 'Done: Make bed')
        ->assertAccessible();

    $routines = collect($screen->get('routines'));
    expect($routines->pluck('name')->all())->toBe(['Get ready', 'Bedtime'])
        ->and(array_column($routines->first()['steps'], 'name'))->toBe(['Make bed', 'Feed the cat', 'Water plants']);
});

it('shows who each of today’s routines is assigned to when the team has several members', function (): void {
    seedSunnyMembers();

    Native::visit('/today')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-subtitle'
            && ($node['props']['text'] ?? null) === 'Morning · 1 of 3')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Household')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-2-subtitle'
            && ($node['props']['text'] ?? null) === 'Sam’s · Evening · 0 of 2')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-2-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Sam Swick')
        ->assertSee('SS')
        ->assertAccessible();
});

it('shows no assignees on today’s routines for a team with one member', function (): void {
    Team::find(1)->update(['members' => [['id' => 7, 'name' => 'Sam Swick', 'initials' => 'SS']]]);

    Native::visit('/today')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-2-subtitle'
            && ($node['props']['text'] ?? null) === 'Evening · 0 of 2')
        ->assertMissingElement('column', fn (array $node): bool => str_ends_with($node['ref'] ?? '', '-owner'));
});

it('shows a routine assigned to someone who left the team as the household’s', function (): void {
    seedSunnyMembers();
    Routine::find(2)->update(['user_id' => 99]);

    Native::visit('/today')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-2-subtitle'
            && ($node['props']['text'] ?? null) === 'Evening · 0 of 2')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-2-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Household');
});

it('shows the routines due today where the team lives', function (): void {
    $this->travelTo(now()->setTime(23, 30));
    Team::find(1)->update(['timezone' => 'Pacific/Auckland']);

    Native::visit('/today')
        ->assertSee('Take out trash')
        ->assertDontSee('Get ready');
});

it('leaves out routines that start after today unless a step is already done', function (): void {
    Routine::find(2)->update(['starts_on' => now()->addDay()->startOfDay()->format('Y-m-d\\TH:i:s.u\\Z')]);
    Routine::find(1)->update(['starts_on' => now()->addDay()->startOfDay()->format('Y-m-d\\TH:i:s.u\\Z')]);

    $screen = Native::visit('/today')
        ->assertSee('Get ready')
        ->assertDontSee('Bedtime');

    expect(array_column($screen->get('routines'), 'name'))->toBe(['Get ready']);
});

it('keeps routines that start today', function (): void {
    Routine::query()->update(['starts_on' => now()->startOfDay()->format('Y-m-d\\TH:i:s.u\\Z')]);

    $screen = Native::visit('/today');

    expect(array_column($screen->get('routines'), 'name'))->toBe(['Get ready', 'Bedtime']);
});

it('applies a routine’s start date to the day being viewed', function (): void {
    Routine::find(3)->update(['starts_on' => now()->addDays(2)->startOfDay()->format('Y-m-d\\TH:i:s.u\\Z')]);

    $screen = Native::visit('/today')->tap('next-day')->assertDontSee('Take out trash');

    Routine::find(3)->update(['starts_on' => now()->addDay()->startOfDay()->format('Y-m-d\\TH:i:s.u\\Z')]);

    $screen->tap('previous-day')->tap('next-day')->assertSee('Take out trash');
});

it('shows an empty state when nothing is due today', function (): void {
    $this->travelTo(now()->addDays(2));

    Native::visit('/today')->assertSee('No routines today');
});

it('ticks a step off on the phone, then sends it to Sunny', function (): void {
    Saloon::fake([UpdateRoutineStepRequest::class => routineStepResponse(12, '2026-09-30T14:00:00.000000Z')]);

    Native::visit('/today')
        ->tap('routine-step-12')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-subtitle'
            && ($node['props']['text'] ?? null) === 'Morning · 2 of 3');

    Saloon::assertSent(fn (UpdateRoutineStepRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routine-occurrences/1/steps/12'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === ['completed' => true]);
    expect(PendingWrite::count())->toBe(0)
        ->and(RoutineOccurrenceStep::find(12)->completed_at->toIso8601String())->toBe('2026-09-30T14:00:00+00:00');
});

it('unticks a completed step', function (): void {
    Saloon::fake([UpdateRoutineStepRequest::class => routineStepResponse(11, null)]);

    Native::visit('/today')
        ->tap('routine-step-11')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-subtitle'
            && ($node['props']['text'] ?? null) === 'Morning · 0 of 3');

    Saloon::assertSent(fn (UpdateRoutineStepRequest $request): bool => $request->body()->all() === ['completed' => false]);
    expect(RoutineOccurrenceStep::find(11)->completed_at)->toBeNull();
});

it('keeps a tick on the phone until Sunny can be reached', function (): void {
    Saloon::fake([UpdateRoutineStepRequest::class => MockResponse::make([], 500)]);

    Native::visit('/today')->tap('routine-step-12');

    expect(RoutineOccurrenceStep::find(12)->isCompleted())->toBeTrue()
        ->and(PendingWrite::sole())->resource->toBe('routine_occurrence_steps')->error->toBeNull()
        ->and(app(SunnySyncCoordinator::class)->isDue())->toBeTrue();
});

it('drops a tick Sunny refuses so the next download restores the step', function (int $status): void {
    Saloon::fake([UpdateRoutineStepRequest::class => MockResponse::make([], $status)]);

    Native::visit('/today')->tap('routine-step-12');

    expect(PendingWrite::count())->toBe(0);
})->with([403, 404, 422]);

it('sends only the latest tick when a step is tapped again before it is sent', function (): void {
    app(SunnyOutbox::class)->queueRoutineStep(12, true);
    app(SunnyOutbox::class)->queueRoutineStep(12, false);

    expect(PendingWrite::sole())
        ->version->toBe(2)
        ->payload->toBe(['routine_occurrence_id' => 1, 'completed' => false]);
});

it('keeps a waiting tick when a download lands before it is sent', function (): void {
    app(SunnyOutbox::class)->queueRoutineStep(12, true);
    Saloon::fake([SyncRequest::class => MockResponse::make([
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => sunnyRoutineOccurrences(), 'synced_at' => now()->toIso8601String(),
    ])]);

    app(SunnySync::class)->sync();

    expect(RoutineOccurrenceStep::find(12)->isCompleted())->toBeTrue()
        ->and(RoutineOccurrenceStep::find(11)->isCompleted())->toBeTrue()
        ->and(RoutineOccurrenceStep::find(13)->isCompleted())->toBeFalse();
});

it('removes routines that are missing from a later download', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make([
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => array_slice(sunnyRoutineOccurrences(), 0, 1), 'synced_at' => now()->toIso8601String(),
    ])]);

    app(SunnySync::class)->sync();

    Native::visit('/today')->assertSee('Bedtime')->assertDontSee('Get ready');
    expect(RoutineOccurrenceStep::count())->toBe(2);
});

it('steps forward to tomorrow from what the phone keeps', function (): void {
    Native::visit('/today')
        ->assertDontSee('Take out trash')
        ->assertElement('row', dayBarWithToday('column'))
        ->tap('next-day')
        ->assertNavTitle('Tomorrow')
        ->assertSee(now()->addDay()->format('l, F j'))
        ->assertSee('Take out trash')
        ->assertDontSee('Get ready')
        ->tap('previous-day')
        ->assertNavTitle('Today')
        ->assertSee('Get ready');

    Saloon::assertNothingSent();
});

it('downloads yesterday’s routines from Sunny', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDay()->toDateString())]);

    $screen = Native::visit('/today')
        ->tap('previous-day')
        ->assertNavTitle('Yesterday')
        ->assertSee(now()->subDay()->format('l, F j'))
        ->assertSee('Get ready')
        ->assertDontSee('Bedtime')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-4-subtitle'
            && ($node['props']['text'] ?? null) === 'Morning · 1 of 2')
        ->assertAccessible();

    Saloon::assertSent(fn (GetRoutineOccurrencesRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routine-occurrences'
        && $request->query()->all() === ['date' => now()->subDay()->toDateString()]);
    expect($screen->get('loading'))->toBeFalse()
        ->and(RoutineOccurrence::where('due_on', now()->subDay()->toDateString())->count())->toBe(1);
});

it('shows who a downloaded day’s routines are assigned to', function (): void {
    seedSunnyMembers();
    Routine::find(1)->update(['user_id' => 7]);
    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDay()->toDateString())]);

    Native::visit('/today')
        ->tap('previous-day')
        ->assertNavTitle('Yesterday')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-4-subtitle'
            && ($node['props']['text'] ?? null) === 'Sam’s · Morning · 1 of 2')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-4-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Sam Swick')
        ->assertAccessible();
});

it('names the day being shown', function (string $today, int $offset, string $title, string $line): void {
    $this->travelTo($today.' 12:00');
    seedSunnyData();
    Saloon::fake([GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => []])]);

    $screen = Native::visit('/today');

    for ($step = 0; $step < abs($offset); $step++) {
        $screen->tap($offset < 0 ? 'previous-day' : 'next-day');
    }

    $screen->assertNavTitle($title)
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routines-date' && ($node['props']['text'] ?? null) === $line)
        ->assertMissingElement('text', fn (array $node): bool => in_array($node['ref'] ?? null, ['day-label', 'day-detail'], true));
})->with([
    'today' => ['2026-11-04', 0, 'Today', 'Wednesday, November 4'],
    'tomorrow' => ['2026-11-04', 1, 'Tomorrow', 'Thursday, November 5'],
    'yesterday' => ['2026-11-04', -1, 'Yesterday', 'Tuesday, November 3'],
    'this week' => ['2026-11-04', -3, 'Sunday', 'November 1 · 3 days ago'],
    'two days ago' => ['2026-11-04', -2, 'Monday', 'November 2 · 2 days ago'],
    'next week' => ['2026-11-04', 6, 'Tuesday', 'November 10 · in 6 days'],
    'a week ago' => ['2026-11-04', -7, 'October 28', 'Wednesday · 7 days ago'],
    'a week from today' => ['2026-11-04', 7, 'November 11', 'Wednesday · in 7 days'],
    'a week after tomorrow' => ['2026-11-04', 8, 'November 12', 'Thursday · in 8 days'],
    'last year' => ['2027-01-03', -7, 'December 27', 'Sunday, 2026 · 7 days ago'],
    'next year' => ['2026-12-28', 8, 'January 5', 'Tuesday, 2027 · in 8 days'],
]);

it('says when nothing was due on a past day', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => []])]);

    Native::visit('/today')
        ->tap('previous-day')
        ->assertSee('No routines yesterday')
        ->assertSee('No routines were scheduled on this day.');
});

it('steps back no further than a week before today', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => []])]);
    $screen = Native::visit('/today');

    foreach (range(1, 7) as $step) {
        $screen->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'previous-day')
            ->tap('previous-day');
    }

    $screen->assertSet('date', now()->subDays(7)->toDateString())
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routines-date' && str_ends_with($node['props']['text'] ?? '', ' · 7 days ago'))
        ->assertElement('row', dayBarWithToday('pressable'))
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'previous-day')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'previous-day' && ($node['style']['opacity'] ?? null) === 0.4)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'next-day')
        ->assertAccessible()
        ->call('previousDay')
        ->assertSet('date', now()->subDays(7)->toDateString())
        ->tap('back-to-today')
        ->assertNavTitle('Today')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'previous-day');
});

it('steps forward no further than a week after tomorrow', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => []])]);
    $screen = Native::visit('/today');

    foreach (range(1, 8) as $step) {
        $screen->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'next-day')
            ->tap('next-day');
    }

    $screen->assertSet('date', now()->addDays(8)->toDateString())
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routines-date' && str_ends_with($node['props']['text'] ?? '', ' · in 8 days'))
        ->assertElement('row', dayBarWithToday('pressable'))
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'next-day')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'next-day' && ($node['style']['opacity'] ?? null) === 0.4)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'previous-day')
        ->assertAccessible()
        ->call('nextDay')
        ->assertSet('date', now()->addDays(8)->toDateString())
        ->tap('back-to-today')
        ->assertNavTitle('Today')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'next-day');
});

it('fades the Today button on today so it can’t be tapped', function (): void {
    Native::visit('/today')
        ->assertElement('row', dayBarWithToday('column'))
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'back-to-today')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'back-to-today'
            && ($node['style']['opacity'] ?? null) === 0.4
            && array_column(array_column($node['children'], 'props'), 'text') === ['Today'])
        ->assertAccessible();
});

it('has no calendar for jumping to a day', function (): void {
    Native::visit('/today')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'pick-day')
        ->assertMissingElement('bottom_sheet')
        ->assertMissingElement('date_picker');
});

it('goes back to today in one tap from either side', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDays(2)->toDateString())]);

    Native::visit('/today')
        ->assertElement('row', dayBarWithToday('column'))
        ->tap('previous-day')
        ->tap('previous-day')
        ->assertNavTitle(now()->subDays(2)->format('l'))
        ->assertElement('row', dayBarWithToday('pressable'))
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'back-to-today'
            && ($node['props']['a11y_label'] ?? null) === 'Back to today'
            && array_column(array_column($node['children'], 'props'), 'text') === ['Today'])
        ->assertAccessible()
        ->tap('back-to-today')
        ->assertNavTitle('Today')
        ->assertSet('date', '')
        ->assertSee('Bedtime')
        ->assertElement('row', dayBarWithToday('column'))
        ->tap('next-day')
        ->tap('next-day')
        ->tap('next-day')
        ->assertNavTitle(now()->addDays(3)->format('l'))
        ->assertElement('row', dayBarWithToday('pressable'))
        ->tap('back-to-today')
        ->assertNavTitle('Today')
        ->assertSet('date', '')
        ->assertSee('Bedtime')
        ->assertElement('row', dayBarWithToday('column'));
});

it('ticks a step off on a past day straight away', function (): void {
    Saloon::fake([
        GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDay()->toDateString()),
        UpdateRoutineStepRequest::class => routineStepResponse(42, '2026-09-29T21:00:00.000000Z'),
    ]);

    Native::visit('/today')
        ->tap('previous-day')
        ->tap('routine-step-42')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-4-subtitle'
            && ($node['props']['text'] ?? null) === 'Morning · 2 of 2')
        ->assertSee('Ticked off just now');

    Saloon::assertSent(fn (UpdateRoutineStepRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routine-occurrences/4/steps/42'
        && $request->body()->all() === ['completed' => true]);
    expect(PendingWrite::count())->toBe(0)
        ->and(RoutineOccurrenceStep::find(42)->completed_at->toIso8601String())->toBe('2026-09-29T21:00:00+00:00');
});

it('sends a past day’s tick to that day once Sunny can be reached', function (): void {
    $yesterday = now()->subDay()->toDateString();
    Saloon::fake([
        GetRoutineOccurrencesRequest::class => routineDayResponse($yesterday),
        UpdateRoutineStepRequest::class => offlineResponse(),
    ]);

    Native::visit('/today')
        ->tap('previous-day')
        ->tap('routine-step-42')
        ->assertSee('Ticked off just now');

    expect(PendingWrite::sole())->payload->toBe(['routine_occurrence_id' => 4, 'completed' => true]);

    Saloon::fake([
        SyncRequest::class => MockResponse::make([
            'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
            'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => sunnyRoutineOccurrences(), 'synced_at' => now()->toIso8601String(),
        ]),
    ]);
    app(SunnySync::class)->sync();

    expect(RoutineOccurrence::where('due_on', $yesterday)->exists())->toBeTrue()
        ->and(RoutineOccurrenceStep::find(42)->isCompleted())->toBeTrue()
        ->and(PendingWrite::count())->toBe(1);

    Saloon::fake([UpdateRoutineStepRequest::class => routineStepResponse(42, '2026-09-29T21:00:00.000000Z')]);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (UpdateRoutineStepRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routine-occurrences/4/steps/42'
        && $request->body()->all() === ['completed' => true]);
    expect(PendingWrite::count())->toBe(0);
});

it('keeps a waiting tick when the same day is downloaded again', function (): void {
    $yesterday = now()->subDay()->toDateString();
    Saloon::fake([
        GetRoutineOccurrencesRequest::class => routineDayResponse($yesterday),
        UpdateRoutineStepRequest::class => offlineResponse(),
    ]);

    $screen = Native::visit('/today')
        ->tap('previous-day')
        ->tap('routine-step-42')
        ->tap('routine-step-41')
        ->call('retry');

    expect(RoutineOccurrenceStep::find(42)->isCompleted())->toBeTrue()
        ->and(RoutineOccurrenceStep::find(41)->isCompleted())->toBeFalse()
        ->and($screen->get('loadError'))->toBe('');
});

it('spins only the first time a day is downloaded', function (): void {
    $yesterday = now()->subDay()->toDateString();
    $loading = [];
    $screen = Native::visit('/today');
    Saloon::fake([GetRoutineOccurrencesRequest::class => function () use (&$screen, &$loading, $yesterday): MockResponse {
        $loading[] = $screen->get('loading');

        return routineDayResponse($yesterday);
    }]);

    $screen->tap('previous-day')
        ->assertSee('Get ready')
        ->tap('next-day')
        ->tap('previous-day')
        ->assertSet('loading', false)
        ->assertSee('Get ready');

    expect($loading)->toBe([true, false]);
    Saloon::assertSentCount(2);
});

it('shows the spinner while a day is downloaded for the first time', function (): void {
    Native::visit('/today')
        ->set('date', now()->subDay()->toDateString())
        ->set('loading', true)
        ->assertSee('Loading yesterday’s routines…')
        ->assertElement('activity_indicator');
});

it('does not spin again for a downloaded day with nothing on it', function (): void {
    $loading = [];
    $screen = Native::visit('/today');
    Saloon::fake([GetRoutineOccurrencesRequest::class => function () use (&$screen, &$loading): MockResponse {
        $loading[] = $screen->get('loading');

        return MockResponse::make(['data' => []]);
    }]);

    $screen->tap('previous-day')
        ->tap('next-day')
        ->tap('previous-day')
        ->assertSee('No routines yesterday');

    expect($loading)->toBe([true, false]);
});

it('keeps showing a downloaded day when it cannot be refreshed', function (MockResponse $response): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDay()->toDateString())]);
    $screen = Native::visit('/today')->tap('previous-day')->tap('next-day');

    Saloon::fake([GetRoutineOccurrencesRequest::class => $response]);

    $screen->tap('previous-day')
        ->assertSet('loading', false)
        ->assertSet('loadError', '')
        ->assertSee('Get ready')
        ->assertDontSee('You’re offline')
        ->assertDontSee('Sunny is unavailable');
})->with([
    'offline' => fn (): MockResponse => offlineResponse(),
    'unavailable' => fn (): MockResponse => MockResponse::make([], 500),
]);

it('keeps a past day on screen when a sync finishes after ticking it', function (): void {
    $yesterday = now()->subDay()->toDateString();
    Saloon::fake([
        GetRoutineOccurrencesRequest::class => routineDayResponse($yesterday),
        UpdateRoutineStepRequest::class => routineStepResponse(42, '2026-09-29T21:00:00.000000Z'),
    ]);
    $screen = Native::visit('/today')->tap('previous-day')->tap('routine-step-42');

    $loading = [];
    Saloon::fake([
        SyncRequest::class => MockResponse::make([
            'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
            'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => sunnyRoutineOccurrences(), 'synced_at' => now()->toIso8601String(),
        ]),
        GetRoutineOccurrencesRequest::class => function () use (&$screen, &$loading, $yesterday): MockResponse {
            $loading[] = $screen->get('loading');

            return routineDayResponse($yesterday);
        },
    ]);
    app(SunnySync::class)->sync();

    expect(RoutineOccurrence::where('due_on', $yesterday)->count())->toBe(1);

    $screen->emitNative('sunny-sync-complete', ['status' => 'finished'])
        ->assertSet('loading', false)
        ->assertSee('Get ready')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-4-subtitle'
            && ($node['props']['text'] ?? null) === 'Morning · 1 of 2');

    expect($loading)->toBe([false]);
});

it('keeps downloaded days through a sync until they are more than a week away', function (): void {
    $store = app(SunnyStore::class);
    $day = fn (int $id, CarbonImmutable $date): array => ['id' => $id, 'routine_id' => 1, 'due_on' => $date->toDateString(), 'routine' => ['id' => 1, 'team_id' => 1, 'name' => 'Day '.$id, 'time_of_day' => 'morning', 'user' => null], 'steps' => [['id' => $id * 10, 'name' => 'Step', 'position' => 1, 'completed_at' => null]]];
    $today = Team::find(1)->today();
    $dates = [100 => $today->subDays(8), 101 => $today->subDays(7), 102 => $today->addDays(8), 103 => $today->addDays(9)];

    foreach ($dates as $id => $date) {
        $store->saveRoutineDay(1, $date->toDateString(), collect([$day($id, $date)]));
    }
    $store->saveRoutineDay(1, $today->subDays(3)->toDateString(), collect());

    Saloon::fake([SyncRequest::class => MockResponse::make([
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => sunnyRoutineOccurrences(), 'synced_at' => now()->toIso8601String(),
    ])]);
    app(SunnySync::class)->sync();

    expect(RoutineOccurrence::whereKey(array_keys($dates))->orderBy('id')->pluck('id')->all())->toBe([101, 102])
        ->and(RoutineOccurrenceStep::whereIn('id', [1000, 1010, 1020, 1030])->orderBy('id')->pluck('id')->all())->toBe([1010, 1020])
        ->and(RoutineOccurrence::whereKey([1, 2, 3])->count())->toBe(3)
        ->and($store->hasRoutineDay(1, $today->subDays(3)->toDateString()))->toBeTrue()
        ->and($store->hasRoutineDay(1, $today->subDays(8)->toDateString()))->toBeFalse()
        ->and($store->hasRoutineDay(1, $today->addDays(9)->toDateString()))->toBeFalse();
});

it('forgets downloaded days when the team is gone or the phone is cleared', function (): void {
    $store = app(SunnyStore::class);
    $yesterday = now()->subDay()->toDateString();
    $store->saveRoutineDay(1, $yesterday, collect(routineDayResponse($yesterday)->body()->all()['data']));

    $store->clear();

    expect(RoutineOccurrence::count())->toBe(0)
        ->and(DB::table('routine_days')->count())->toBe(0);

    seedSunnyData();
    $store->saveRoutineDay(1, $yesterday, collect());
    Saloon::fake([SyncRequest::class => MockResponse::make([
        'teams' => [['id' => 2, 'name' => 'Other', 'slug' => 'other']],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [], 'synced_at' => now()->toIso8601String(),
    ])]);
    app(SunnySync::class)->sync();

    expect(RoutineOccurrence::count())->toBe(0)
        ->and($store->hasRoutineDay(1, $yesterday))->toBeFalse();
});

it('shows that other days need a connection when offline', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => offlineResponse()]);

    $screen = Native::visit('/today')
        ->tap('previous-day')
        ->tap('previous-day')
        ->assertNavTitle(now()->subDays(2)->format('l'))
        ->assertSee('You’re offline')
        ->assertSee('Your phone only keeps today and tomorrow. Connect to see '.now()->subDays(2)->format('l').'’s routines and tick things off.')
        ->assertSee('Try again')
        ->assertElement('row', dayBarWithToday('pressable'))
        ->assertAccessible();

    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDays(2)->toDateString())]);

    $screen->tap('day-retry')
        ->assertDontSee('You’re offline')
        ->assertSee('Get ready')
        ->tap('back-to-today')
        ->assertNavTitle('Today');
});

it('keeps the bar’s Today button working when a day fails to load', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => offlineResponse()]);

    Native::visit('/today')
        ->tap('previous-day')
        ->assertSee('You’re offline')
        ->assertElement('row', dayBarWithToday('pressable'))
        ->tap('back-to-today')
        ->assertNavTitle('Today')
        ->assertSet('loadError', '')
        ->assertElement('row', dayBarWithToday('column'));
});

it('takes the offline card back to today', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => offlineResponse()]);

    Native::visit('/today')
        ->tap('previous-day')
        ->assertSee('Connect to see yesterday’s routines')
        ->tap('day-back-to-today')
        ->assertNavTitle('Today')
        ->assertSet('loadError', '')
        ->assertSee('Get ready');
});

it('says when Sunny cannot send another day', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => MockResponse::make([], 500)]);

    Native::visit('/today')
        ->tap('previous-day')
        ->assertSee('Sunny is unavailable')
        ->assertDontSee('You’re offline');
});

it('ignores routines from another team or day in a download', function (): void {
    $yesterday = now()->subDay()->toDateString();
    Saloon::fake([GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => [
        ...routineDayResponse($yesterday)->body()->all()['data'],
        ['id' => 5, 'routine_id' => 5, 'due_on' => $yesterday, 'routine' => ['id' => 5, 'team_id' => 2, 'name' => 'Other team', 'time_of_day' => 'morning', 'user' => null], 'steps' => []],
        ['id' => 6, 'routine_id' => 6, 'due_on' => now()->subDays(5)->toDateString(), 'routine' => ['id' => 6, 'team_id' => 1, 'name' => 'Wrong day', 'time_of_day' => 'morning', 'user' => null], 'steps' => []],
    ]])]);

    Native::visit('/today')
        ->tap('previous-day')
        ->assertSee('Get ready')
        ->assertDontSee('Other team')
        ->assertDontSee('Wrong day');

    expect(RoutineOccurrence::whereKey([5, 6])->exists())->toBeFalse();
});
