<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\DeleteRoutine;
use App\Models\Routine;
use App\Models\User;

test('it deletes a routine on the current team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create(['name' => 'Bedtime']);

    SunnyServer::actingAs($user)
        ->tool(DeleteRoutine::class, ['id' => $routine->id])
        ->assertOk()
        ->assertSee('Bedtime');

    expect($routine->fresh())->toBeTrashed();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(DeleteRoutine::class)
        ->assertHasErrors();
});

test('it cannot delete routines from other teams', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(DeleteRoutine::class, ['id' => $routine->id])
        ->assertHasErrors(['Routine not found.']);

    expect($routine->fresh()->trashed())->toBeFalse();
});
