<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\GetChecklist;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it returns a checklist with its items in position order', function () {
    $user = User::factory()->create(['name' => 'Andy']);
    $checklist = Checklist::factory()->shopping()->for($user->currentTeam)->create(['name' => 'Groceries']);
    $eggs = ChecklistItem::factory()->for($checklist)->create(['name' => 'Eggs', 'position' => 2]);
    $milk = ChecklistItem::factory()->for($checklist)->completed($user)->create(['name' => 'Milk', 'position' => 1]);

    SunnyServer::actingAs($user)
        ->tool(GetChecklist::class, ['id' => $checklist->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $checklist->id)
            ->where('name', 'Groceries')
            ->where('type', 'shopping')
            ->where('user_id', null)
            ->where('owner_name', null)
            ->where('item_count', 2)
            ->where('completed_count', 1)
            ->where('progress', 50)
            ->where('created_at', $checklist->created_at->toIso8601String())
            ->where('updated_at', $checklist->updated_at->toIso8601String())
            ->has('items', 2)
            ->where('items.0', [
                'id' => $milk->id,
                'name' => 'Milk',
                'position' => 1,
                'completed' => true,
                'completed_at' => $milk->completed_at->toIso8601String(),
                'completed_by_name' => 'Andy',
            ])
            ->where('items.1', [
                'id' => $eggs->id,
                'name' => 'Eggs',
                'position' => 2,
                'completed' => false,
                'completed_at' => null,
                'completed_by_name' => null,
            ]));
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetChecklist::class)
        ->assertHasErrors();
});

test('it does not return checklists from other teams', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetChecklist::class, ['id' => $checklist->id])
        ->assertHasErrors(['Checklist not found.']);
});

test('it does not return deleted checklists', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $checklist->delete();

    SunnyServer::actingAs($user)
        ->tool(GetChecklist::class, ['id' => $checklist->id])
        ->assertHasErrors(['Checklist not found.']);
});
