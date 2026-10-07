<?php

use App\Mcp\Tools\Lists\ClearCompletedChecklistItems;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it removes only the completed items', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries']);
    ChecklistItem::factory()->for($checklist)->completed($user)->count(2)->create();
    $remaining = ChecklistItem::factory()->for($checklist)->create(['name' => 'Eggs']);

    SunnyTestServer::actingAs($user)
        ->tool(ClearCompletedChecklistItems::class, ['id' => $checklist->id])
        ->assertOk()
        ->assertSee('Removed 2 completed items from the list "Groceries".');

    expect($checklist->items()->pluck('id')->all())->toBe([$remaining->id]);
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(ClearCompletedChecklistItems::class)
        ->assertHasErrors();
});

test('it does not clear checklists from other teams', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->completed()->create();

    SunnyTestServer::actingAs($user)
        ->tool(ClearCompletedChecklistItems::class, ['id' => $item->checklist_id])
        ->assertHasErrors(['Checklist not found.']);

    expect($item->fresh())->not->toBeNull();
});
