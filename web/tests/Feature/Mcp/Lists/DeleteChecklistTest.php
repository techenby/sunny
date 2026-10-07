<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\DeleteChecklist;
use App\Models\Checklist;
use App\Models\User;

test('it soft deletes a checklist on the current team', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries']);

    SunnyServer::actingAs($user)
        ->tool(DeleteChecklist::class, ['id' => $checklist->id])
        ->assertOk()
        ->assertSee('Groceries');

    expect($checklist->fresh())->toBeTrashed();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(DeleteChecklist::class)
        ->assertHasErrors();
});

test('it cannot delete checklists from other teams', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(DeleteChecklist::class, ['id' => $checklist->id])
        ->assertHasErrors(['Checklist not found.']);

    expect($checklist->fresh()->trashed())->toBeFalse();
});
