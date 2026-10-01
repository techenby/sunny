<?php

use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\Requests\UpdateRoutineStepRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\PendingWrite;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

function routineStepResponse(int $id, ?string $completedAt): MockResponse
{
    return MockResponse::make(['data' => ['id' => $id, 'name' => 'Feed the cat', 'position' => 2, 'completed_at' => $completedAt, 'completed_by' => 7]]);
}

it('lists today’s routines by time of day with their progress', function (): void {
    $screen = Native::visit('/routines')
        ->assertNavTitle('Routines')
        ->assertSee(now()->format('l, F j'))
        ->assertSee('Get ready')
        ->assertSee('Morning · Household')
        ->assertSee('Bedtime')
        ->assertSee('Evening · Sam')
        ->assertDontSee('Take out trash')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-progress'
            && ($node['props']['text'] ?? null) === '1 of 3')
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

    Native::visit('/routines')
        ->assertSee('Take out trash')
        ->assertDontSee('Get ready');
});

it('shows an empty state when nothing is due today', function (): void {
    $this->travelTo(now()->addDays(2));

    Native::visit('/routines')->assertSee('No routines today');
});

it('ticks a step off on the phone, then sends it to Sunny', function (): void {
    Saloon::fake([UpdateRoutineStepRequest::class => routineStepResponse(12, '2026-09-30T14:00:00.000000Z')]);

    Native::visit('/routines')
        ->tap('routine-step-12')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-progress'
            && ($node['props']['text'] ?? null) === '2 of 3');

    Saloon::assertSent(fn (UpdateRoutineStepRequest $request): bool => $request->resolveEndpoint() === '/teams/family/routine-occurrences/1/steps/12'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === ['completed' => true]);
    expect(PendingWrite::count())->toBe(0)
        ->and(RoutineOccurrenceStep::find(12)->completed_at->toIso8601String())->toBe('2026-09-30T14:00:00+00:00');
});

it('unticks a completed step', function (): void {
    Saloon::fake([UpdateRoutineStepRequest::class => routineStepResponse(11, null)]);

    Native::visit('/routines')
        ->tap('routine-step-11')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'routine-1-progress'
            && ($node['props']['text'] ?? null) === '0 of 3');

    Saloon::assertSent(fn (UpdateRoutineStepRequest $request): bool => $request->body()->all() === ['completed' => false]);
    expect(RoutineOccurrenceStep::find(11)->completed_at)->toBeNull();
});

it('keeps a tick on the phone until Sunny can be reached', function (): void {
    Saloon::fake([UpdateRoutineStepRequest::class => MockResponse::make([], 500)]);

    Native::visit('/routines')->tap('routine-step-12');

    expect(RoutineOccurrenceStep::find(12)->isCompleted())->toBeTrue()
        ->and(PendingWrite::sole())->resource->toBe('routine_occurrence_steps')->error->toBeNull()
        ->and(app(SunnySyncCoordinator::class)->isDue())->toBeTrue();
});

it('drops a tick Sunny refuses so the next download restores the step', function (int $status): void {
    Saloon::fake([UpdateRoutineStepRequest::class => MockResponse::make([], $status)]);

    Native::visit('/routines')->tap('routine-step-12');

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

    Native::visit('/routines')->assertSee('Bedtime')->assertDontSee('Get ready');
    expect(RoutineOccurrenceStep::count())->toBe(2);
});
