<?php

use App\Mcp\Tools\Routines\DeleteRoutine;
use App\Models\Routine;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it deletes a routine on the current team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create(['name' => 'Bedtime']);

    SunnyTestServer::actingAs($user)
        ->tool(DeleteRoutine::class, ['id' => $routine->id])
        ->assertOk()
        ->assertSee('Bedtime');

    expect($routine->fresh())->toBeTrashed();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteRoutine::class)
        ->assertHasErrors();
});

test('it cannot delete routines from other teams', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteRoutine::class, ['id' => $routine->id])
        ->assertHasErrors(['Routine not found.']);

    expect($routine->fresh()->trashed())->toBeFalse();
});
