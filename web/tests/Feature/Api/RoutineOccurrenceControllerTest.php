<?php

use App\Enums\TimeOfDay;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    Date::setTestNow('2026-09-30 12:00:00');
});

test('guests cannot access routine occurrences', function () {
    $this->getJson(route('api.routine-occurrences.index', 'household'))->assertUnauthorized();
    $this->patchJson(route('api.routine-occurrences.steps.update', ['household', 1, 1]))->assertUnauthorized();
});

test('index generates and returns the routines due today', function () {
    $user = User::factory()->create();
    $morning = Routine::factory()->for($user->currentTeam)->daily()->create(['name' => 'Wake up']);
    RoutineStep::factory()->for($morning)->create(['name' => 'Make bed', 'position' => 2]);
    RoutineStep::factory()->for($morning)->create(['name' => 'Get dressed', 'position' => 1]);
    Routine::factory()->for($user->currentTeam)->daily()->timeOfDay(TimeOfDay::Evening)->create(['name' => 'Wind down']);
    Routine::factory()->for($user->currentTeam)->daily()->inactive()->create();
    Routine::factory()->daily()->create();

    $this->actingAs($user)
        ->getJson(route('api.routine-occurrences.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.due_on', '2026-09-30')
        ->assertJsonPath('data.0.routine.name', 'Wake up')
        ->assertJsonPath('data.0.steps.0.name', 'Get dressed')
        ->assertJsonPath('data.0.steps.0.completed_at', null)
        ->assertJsonPath('data.0.steps.1.name', 'Make bed')
        ->assertJsonPath('data.1.routine.name', 'Wind down');

    expect(RoutineOccurrence::count())->toBe(2);
});

test('index uses the team timezone for today', function () {
    Date::setTestNow('2026-10-01 02:00:00');
    $user = User::factory()->create();
    $user->currentTeam->update(['timezone' => 'America/Chicago']);
    Routine::factory()->for($user->currentTeam)->daily()->create();

    $this->actingAs($user)
        ->getJson(route('api.routine-occurrences.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonPath('data.0.due_on', '2026-09-30');
});

test('index returns the routines for a given date', function () {
    $user = User::factory()->create();
    Routine::factory()->for($user->currentTeam)->monthly(15)->create(['starts_on' => '2026-01-01']);

    $this->actingAs($user)
        ->getJson(route('api.routine-occurrences.index', [$user->currentTeam, 'date' => '2026-10-15']))
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.due_on', '2026-10-15');

    $this->actingAs($user)
        ->getJson(route('api.routine-occurrences.index', [$user->currentTeam, 'date' => '2026-10-16']))
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('index validates the date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.routine-occurrences.index', [$user->currentTeam, 'date' => 'tomorrow']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('date');
});

test('index returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.routine-occurrences.index', $routine->team))
        ->assertForbidden();
});

test('update completes a step', function () {
    $user = User::factory()->create();
    $occurrenceStep = occurrenceStepFor($user);

    $this->actingAs($user)
        ->patchJson(route('api.routine-occurrences.steps.update', [$user->currentTeam, $occurrenceStep->occurrence, $occurrenceStep]), ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.id', $occurrenceStep->id)
        ->assertJsonPath('data.completed_by', $user->id)
        ->assertJsonPath('data.completed_at', fn ($value) => $value !== null);

    expect($occurrenceStep->fresh())
        ->completed_at->not->toBeNull()
        ->completed_by->toBe($user->id);
});

test('update keeps the original completion when a step is completed again', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $occurrenceStep = occurrenceStepFor($user, ['completed_at' => now()->subHour(), 'completed_by' => $other->id]);

    $this->actingAs($user)
        ->patchJson(route('api.routine-occurrences.steps.update', [$user->currentTeam, $occurrenceStep->occurrence, $occurrenceStep]), ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.completed_by', $other->id);

    expect($occurrenceStep->fresh()->completed_at->equalTo(now()->subHour()))->toBeTrue();
});

test('update uncompletes a step', function () {
    $user = User::factory()->create();
    $occurrenceStep = occurrenceStepFor($user, ['completed_at' => now(), 'completed_by' => $user->id]);

    $this->actingAs($user)
        ->patchJson(route('api.routine-occurrences.steps.update', [$user->currentTeam, $occurrenceStep->occurrence, $occurrenceStep]), ['completed' => false])
        ->assertOk()
        ->assertJsonPath('data.completed_at', null)
        ->assertJsonPath('data.completed_by', null);
});

test('update validates completed', function () {
    $user = User::factory()->create();
    $occurrenceStep = occurrenceStepFor($user);

    $this->actingAs($user)
        ->patchJson(route('api.routine-occurrences.steps.update', [$user->currentTeam, $occurrenceStep->occurrence, $occurrenceStep]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('completed');
});

test('update returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $occurrenceStep = occurrenceStepFor(User::factory()->create());

    $this->actingAs($user)
        ->patchJson(route('api.routine-occurrences.steps.update', [$occurrenceStep->occurrence->routine->team, $occurrenceStep->occurrence, $occurrenceStep]), ['completed' => true])
        ->assertForbidden();
});

test('update returns 404 for an occurrence under a different team', function () {
    $user = User::factory()->create();
    $occurrenceStep = occurrenceStepFor(User::factory()->create());

    $this->actingAs($user)
        ->patchJson(route('api.routine-occurrences.steps.update', [$user->currentTeam, $occurrenceStep->occurrence, $occurrenceStep]), ['completed' => true])
        ->assertNotFound();
});

test('update returns 404 for a step from a different occurrence', function () {
    $user = User::factory()->create();
    $occurrenceStep = occurrenceStepFor($user);
    $otherStep = occurrenceStepFor($user);

    $this->actingAs($user)
        ->patchJson(route('api.routine-occurrences.steps.update', [$user->currentTeam, $occurrenceStep->occurrence, $otherStep]), ['completed' => true])
        ->assertNotFound();
});

/** @param array<string, mixed> $attributes */
function occurrenceStepFor(User $user, array $attributes = []): RoutineOccurrenceStep
{
    $routine = Routine::factory()->for($user->currentTeam)->create();

    return RoutineOccurrenceStep::factory()
        ->for(RoutineOccurrence::factory()->for($routine), 'occurrence')
        ->for(RoutineStep::factory()->for($routine), 'step')
        ->create($attributes);
}
