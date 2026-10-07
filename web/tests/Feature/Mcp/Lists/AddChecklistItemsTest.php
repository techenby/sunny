<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\AddChecklistItems;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;

test('it adds items to the end of a checklist', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->shopping()->for($user->currentTeam)->create();
    ChecklistItem::factory()->for($checklist)->create(['name' => 'Bread', 'position' => 1]);

    $response = SunnyServer::actingAs($user)
        ->tool(AddChecklistItems::class, ['checklist_id' => $checklist->id, 'items' => ['Milk', 'Eggs']]);

    $milk = $checklist->items()->firstWhere('name', 'Milk');
    $eggs = $checklist->items()->firstWhere('name', 'Eggs');

    $response
        ->assertOk()
        ->assertStructuredContent([
            'checklist_id' => $checklist->id,
            'count' => 2,
            'items' => [
                ['id' => $milk->id, 'name' => 'Milk', 'position' => 2, 'completed' => false, 'completed_at' => null, 'completed_by_name' => null],
                ['id' => $eggs->id, 'name' => 'Eggs', 'position' => 3, 'completed' => false, 'completed_at' => null, 'completed_by_name' => null],
            ],
        ]);

    expect($checklist->items()->pluck('name')->all())->toBe(['Bread', 'Milk', 'Eggs']);
});

test('it requires at least one item', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(AddChecklistItems::class, ['checklist_id' => $checklist->id, 'items' => []])
        ->assertHasErrors(['Provide at least one item name to add.']);
});

test('it rejects item names that are too long', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(AddChecklistItems::class, ['checklist_id' => $checklist->id, 'items' => [str_repeat('a', 256)]])
        ->assertHasErrors(['Each item name may not be longer than 255 characters.']);

    expect($checklist->items()->count())->toBe(0);
});

test('it cannot add items to checklists from other teams', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(AddChecklistItems::class, ['checklist_id' => $checklist->id, 'items' => ['Milk']])
        ->assertHasErrors(['Checklist not found.']);

    expect($checklist->items()->count())->toBe(0);
});
