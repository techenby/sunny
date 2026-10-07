<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\CompleteRoutineStep;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-30 07:30', 'America/Chicago'));
});

test('it completes a step and returns the updated progress', function () {
    $user = User::factory()->create(['name' => 'Ada']);
    $routine = Routine::factory()->for($user->currentTeam)->create(['name' => 'Morning routine']);
    $occurrence = RoutineOccurrence::factory()->for($routine)->dueOn(CarbonImmutable::parse('2026-09-30'))->create();
    $step = RoutineOccurrenceStep::factory()->for($occurrence, 'occurrence')
        ->for(RoutineStep::factory()->for($routine)->state(['name' => 'Brush teeth']), 'step')
        ->create();
    RoutineOccurrenceStep::factory()->for($occurrence, 'occurrence')
        ->for(RoutineStep::factory()->for($routine), 'step')
        ->create();

    SunnyServer::actingAs($user)
        ->tool(CompleteRoutineStep::class, ['occurrence_step_id' => $step->id])
        ->assertOk()
        ->assertStructuredContent([
            'step' => [
                'occurrence_step_id' => $step->id,
                'name' => 'Brush teeth',
                'completed' => true,
                'completed_at' => '2026-09-30T07:30:00-05:00',
                'completed_by' => $user->id,
                'completed_by_name' => 'Ada',
            ],
            'occurrence' => [
                'occurrence_id' => $occurrence->id,
                'routine_id' => $routine->id,
                'routine_name' => 'Morning routine',
                'time_of_day' => 'morning',
                'owner_name' => null,
                'due_on' => '2026-09-30',
                'progress' => 50,
                'completed' => false,
            ],
        ]);

    expect($step->refresh()->completed_by)->toBe($user->id);
});

test('it uncompletes a step', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $occurrence = RoutineOccurrence::factory()->for($routine)->create();
    $step = RoutineOccurrenceStep::factory()->for($occurrence, 'occurrence')
        ->for(RoutineStep::factory()->for($routine), 'step')
        ->completed($user)
        ->create();

    SunnyServer::actingAs($user)
        ->tool(CompleteRoutineStep::class, ['occurrence_step_id' => $step->id, 'completed' => false])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('step.completed', false)
            ->where('step.completed_at', null)
            ->where('occurrence.progress', 0)
            ->where('occurrence.completed', false)
            ->etc());

    expect($step->refresh()->isCompleted())->toBeFalse();
});

test('completing every step completes the occurrence', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $step = RoutineOccurrenceStep::factory()
        ->for(RoutineOccurrence::factory()->for($routine), 'occurrence')
        ->for(RoutineStep::factory()->for($routine), 'step')
        ->create();

    SunnyServer::actingAs($user)
        ->tool(CompleteRoutineStep::class, ['occurrence_step_id' => $step->id, 'completed' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('occurrence.progress', 100)
            ->where('occurrence.completed', true)
            ->etc());
});

test('completing an already completed step keeps the original completion', function () {
    $user = User::factory()->create();
    $other = User::factory()->memberOf($user->currentTeam)->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $step = RoutineOccurrenceStep::factory()
        ->for(RoutineOccurrence::factory()->for($routine), 'occurrence')
        ->for(RoutineStep::factory()->for($routine), 'step')
        ->completed($other)
        ->create();

    SunnyServer::actingAs($user)
        ->tool(CompleteRoutineStep::class, ['occurrence_step_id' => $step->id])
        ->assertOk();

    expect($step->refresh()->completed_by)->toBe($other->id);
});

test('it requires an occurrence step id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CompleteRoutineStep::class)
        ->assertHasErrors();
});

test('it cannot complete steps on routines from other teams', function () {
    $user = User::factory()->create();
    $step = RoutineOccurrenceStep::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CompleteRoutineStep::class, ['occurrence_step_id' => $step->id])
        ->assertHasErrors(['Routine step not found.']);

    expect($step->refresh()->isCompleted())->toBeFalse();
});
