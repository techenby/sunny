<?php

use App\Mcp\Tools\Lists\DeleteChecklist;
use App\Models\Checklist;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it soft deletes a checklist on the current team', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries']);

    SunnyTestServer::actingAs($user)
        ->tool(DeleteChecklist::class, ['id' => $checklist->id])
        ->assertOk()
        ->assertSee('Groceries');

    expect($checklist->fresh())->toBeTrashed();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteChecklist::class)
        ->assertHasErrors();
});

test('it cannot delete checklists from other teams', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteChecklist::class, ['id' => $checklist->id])
        ->assertHasErrors(['Checklist not found.']);

    expect($checklist->fresh()->trashed())->toBeFalse();
});
