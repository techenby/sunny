<?php

use App\Mcp\Tools\Lists\RemoveChecklistItem;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it removes an item from a checklist', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries']);
    $item = ChecklistItem::factory()->for($checklist)->create(['name' => 'Milk']);

    SunnyTestServer::actingAs($user)
        ->tool(RemoveChecklistItem::class, ['checklist_id' => $checklist->id, 'item_id' => $item->id])
        ->assertOk()
        ->assertSee('Removed "Milk" from the list "Groceries".');

    expect($item->fresh())->toBeNull();
});

test('it requires a checklist id and an item id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(RemoveChecklistItem::class)
        ->assertHasErrors();
});

test('it does not remove items on another checklist', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->create();

    SunnyTestServer::actingAs($user)
        ->tool(RemoveChecklistItem::class, ['checklist_id' => $checklist->id, 'item_id' => $item->id])
        ->assertHasErrors(['Checklist item not found.']);

    expect($item->fresh())->not->toBeNull();
});

test('it does not remove items from other teams', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(RemoveChecklistItem::class, ['checklist_id' => $item->checklist_id, 'item_id' => $item->id])
        ->assertHasErrors(['Checklist not found.']);

    expect($item->fresh())->not->toBeNull();
});
