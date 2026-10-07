<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\RemoveRoutineStep;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;

test('it soft deletes a step', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create(['name' => 'Bedtime']);
    $step = RoutineStep::factory()->for($routine)->create(['name' => 'Read a story']);

    SunnyServer::actingAs($user)
        ->tool(RemoveRoutineStep::class, ['routine_id' => $routine->id, 'step_id' => $step->id])
        ->assertOk()
        ->assertSee('Read a story');

    expect($step->fresh())->toBeTrashed();
});

test('it requires a step id', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(RemoveRoutineStep::class, ['routine_id' => $routine->id])
        ->assertHasErrors();
});

test('it cannot remove a step from another routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $step = RoutineStep::factory()->for(Routine::factory()->for($user->currentTeam))->create();

    SunnyServer::actingAs($user)
        ->tool(RemoveRoutineStep::class, ['routine_id' => $routine->id, 'step_id' => $step->id])
        ->assertHasErrors(['Step not found on this routine.']);

    expect($step->fresh()->trashed())->toBeFalse();
});

test('it cannot remove steps on routines from other teams', function () {
    $user = User::factory()->create();
    $step = RoutineStep::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(RemoveRoutineStep::class, ['routine_id' => $step->routine_id, 'step_id' => $step->id])
        ->assertHasErrors(['Routine not found.']);

    expect($step->fresh()->trashed())->toBeFalse();
});
