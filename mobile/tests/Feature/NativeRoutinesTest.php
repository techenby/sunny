<?php

use App\Http\Integrations\Sunny\Requests\GetRoutineOccurrencesRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\Requests\UpdateRoutineStepRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\PendingWrite;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
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

function routineStepResponse(int $id, ?string $completedAt): MockResponse
{
    return MockResponse::make(['data' => ['id' => $id, 'name' => 'Feed the cat', 'position' => 2, 'completed_at' => $completedAt, 'completed_by' => 7]]);
}

it('lists today’s routines by time of day with their progress', function (): void {
    $screen = Native::visit('/today')
        ->assertNavTitle('Today')
        ->assertSee(now()->format('l, F j'))
        ->assertSee('Get ready')
        ->assertSee('Morning · Household')
        ->assertSee('Bedtime')
        ->assertSee('Evening · Sam')
        ->assertDontSee('Take out trash')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-subtitle'
            && ($node['props']['text'] ?? null) === 'Morning · Household · 1 of 3')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-step-11'
            && ($node['props']['a11y_label'] ?? null) === 'Done: Make bed')
        ->assertAccessible();

    $routines = collect($screen->get('routines'));
    expect($routines->pluck('name')->all())->toBe(['Get ready', 'Bedtime'])
        ->and(array_column($routines->first()['steps'], 'name'))->toBe(['Make bed', 'Feed the cat', 'Water plants']);
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
            && ($node['props']['text'] ?? null) === 'Morning · Household · 2 of 3');

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
            && ($node['props']['text'] ?? null) === 'Morning · Household · 0 of 3');

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
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'back-to-today')
        ->tap('next-day')
        ->assertNavTitle('Tomorrow')
        ->assertSee(now()->addDay()->format('l, F j'))
        ->assertSee(now()->addDay()->format('D, M j'))
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
            && ($node['props']['text'] ?? null) === 'Morning · Household · 1 of 2')
        ->assertAccessible();

    Saloon::assertSent(fn (GetRoutineOccurrencesRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routine-occurrences'
        && $request->query()->all() === ['date' => now()->subDay()->toDateString()]);
    expect($screen->get('loading'))->toBeFalse()
        ->and(RoutineOccurrence::where('due_on', now()->subDay()->toDateString())->count())->toBe(1);
});

it('names the day being shown', function (string $date, string $title, string $line, string $bar, string $detail): void {
    $this->travelTo('2026-11-04 12:00');
    seedSunnyData();
    Saloon::fake([GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => []])]);

    Native::visit('/today')
        ->call('pickDay', $date)
        ->assertNavTitle($title)
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routines-date' && ($node['props']['text'] ?? null) === $line)
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'day-label' && ($node['props']['text'] ?? null) === $bar)
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'day-detail' && ($node['props']['text'] ?? null) === $detail);
})->with([
    'today' => ['2026-11-04', 'Today', 'Wednesday, November 4', 'Today', 'Wed, Nov 4'],
    'tomorrow' => ['2026-11-05', 'Tomorrow', 'Thursday, November 5', 'Tomorrow', 'Thu, Nov 5'],
    'yesterday' => ['2026-11-03', 'Yesterday', 'Tuesday, November 3', 'Yesterday', 'Tue, Nov 3'],
    'this week' => ['2026-11-01', 'Sunday', 'November 1', 'Sun, Nov 1', '3 days ago'],
    'next week' => ['2026-11-10', 'Tuesday', 'November 10', 'Tue, Nov 10', 'in 6 days'],
    'further off' => ['2026-10-12', 'October 12', 'Monday', 'Mon, Oct 12', '23 days ago'],
    'another year' => ['2025-12-25', 'December 25', 'Thursday, 2025', 'Thu, Dec 25', '314 days ago'],
]);

it('says when nothing was due on a past day', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => MockResponse::make(['data' => []])]);

    Native::visit('/today')
        ->tap('previous-day')
        ->assertSee('No routines yesterday')
        ->assertSee('No routines were scheduled on this day.');
});

it('jumps to a day picked from the calendar', function (): void {
    $date = now()->subDays(20);
    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse($date->toDateString(), 'Spring clean')]);

    Native::visit('/today')
        ->tap('pick-day')
        ->assertSet('choosingDay', true)
        ->pickDate('day-picker', $date)
        ->assertSet('choosingDay', false)
        ->assertSet('date', $date->toDateString())
        ->assertNavTitle($date->format('F j'))
        ->assertSee('Spring clean')
        ->assertSee('20 days ago');
});

it('closes the calendar without changing the day', function (): void {
    Native::visit('/today')
        ->tap('pick-day')
        ->tap('day-picker-cancel')
        ->assertSet('choosingDay', false)
        ->assertSet('date', '')
        ->assertNavTitle('Today');
});

it('goes back to today in one tap', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDays(2)->toDateString())]);

    Native::visit('/today')
        ->tap('previous-day')
        ->tap('previous-day')
        ->assertNavTitle(now()->subDays(2)->format('l'))
        ->tap('back-to-today')
        ->assertNavTitle('Today')
        ->assertSet('date', '')
        ->assertSee('Bedtime')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'back-to-today');
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
            && ($node['props']['text'] ?? null) === 'Morning · Household · 2 of 2')
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

    expect(RoutineOccurrence::where('due_on', $yesterday)->exists())->toBeFalse()
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

it('downloads the day again after a sync clears it', function (): void {
    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDay()->toDateString())]);
    $screen = Native::visit('/today')->tap('previous-day');

    RoutineOccurrence::where('id', 4)->delete();

    $screen->emitNative('sunny-sync-complete', ['status' => 'finished'])
        ->assertSee('Get ready');

    Saloon::assertSentCount(2);
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
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'back-to-today')
        ->assertAccessible();

    Saloon::fake([GetRoutineOccurrencesRequest::class => routineDayResponse(now()->subDays(2)->toDateString())]);

    $screen->tap('day-retry')
        ->assertDontSee('You’re offline')
        ->assertSee('Get ready')
        ->tap('back-to-today')
        ->assertNavTitle('Today');
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
