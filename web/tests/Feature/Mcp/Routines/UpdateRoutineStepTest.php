<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\UpdateRoutineStep;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;

test('it renames a step', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $step = RoutineStep::factory()->for($routine)->create(['name' => 'Brush teeth', 'position' => 1]);

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutineStep::class, ['routine_id' => $routine->id, 'step_id' => $step->id, 'name' => 'Brush teeth and floss'])
        ->assertOk()
        ->assertStructuredContent(['id' => $step->id, 'name' => 'Brush teeth and floss', 'position' => 1]);

    expect($step->refresh()->name)->toBe('Brush teeth and floss');
});

test('it requires a name', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $step = RoutineStep::factory()->for($routine)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutineStep::class, ['routine_id' => $routine->id, 'step_id' => $step->id])
        ->assertHasErrors();
});

test('it cannot rename a step from another routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $step = RoutineStep::factory()->for(Routine::factory()->for($user->currentTeam))->create(['name' => 'Original']);

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutineStep::class, ['routine_id' => $routine->id, 'step_id' => $step->id, 'name' => 'Changed'])
        ->assertHasErrors(['Step not found on this routine.']);

    expect($step->refresh()->name)->toBe('Original');
});

test('it cannot rename steps on routines from other teams', function () {
    $user = User::factory()->create();
    $step = RoutineStep::factory()->create(['name' => 'Original']);

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutineStep::class, ['routine_id' => $step->routine_id, 'step_id' => $step->id, 'name' => 'Changed'])
        ->assertHasErrors(['Routine not found.']);

    expect($step->refresh()->name)->toBe('Original');
});
