<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\ListChecklists;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it lists the current team checklists ordered by name with progress', function () {
    $user = User::factory()->create(['name' => 'Andy']);
    $groceries = Checklist::factory()->shopping()->for($user->currentTeam)->create(['name' => 'Groceries']);
    ChecklistItem::factory()->for($groceries)->completed($user)->create();
    ChecklistItem::factory()->for($groceries)->count(3)->create();
    $chores = Checklist::factory()->todo()->for($user->currentTeam)->ownedBy($user)->create(['name' => 'Chores']);

    SunnyServer::actingAs($user)
        ->tool(ListChecklists::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 2)
            ->has('checklists', 2)
            ->has('checklists.0', fn (AssertableJson $json) => $json
                ->where('id', $chores->id)
                ->where('name', 'Chores')
                ->where('type', 'todo')
                ->where('user_id', $user->id)
                ->where('owner_name', 'Andy')
                ->where('item_count', 0)
                ->where('completed_count', 0)
                ->where('progress', 0))
            ->has('checklists.1', fn (AssertableJson $json) => $json
                ->where('id', $groceries->id)
                ->where('name', 'Groceries')
                ->where('type', 'shopping')
                ->where('user_id', null)
                ->where('owner_name', null)
                ->where('item_count', 4)
                ->where('completed_count', 1)
                ->where('progress', 25)));
});

test('it filters by type', function () {
    $user = User::factory()->create();
    Checklist::factory()->shopping()->for($user->currentTeam)->create(['name' => 'Groceries']);
    Checklist::factory()->todo()->for($user->currentTeam)->create(['name' => 'Chores']);

    SunnyServer::actingAs($user)
        ->tool(ListChecklists::class, ['type' => 'shopping'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->where('checklists.0.name', 'Groceries')
            ->etc());
});

test('it rejects an invalid type', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ListChecklists::class, ['type' => 'groceries'])
        ->assertHasErrors(['The type must be one of: todo, shopping, wishlist.']);
});

test('it does not list checklists from other teams', function () {
    $user = User::factory()->create();
    Checklist::factory()->create(['name' => 'Secret List']);

    SunnyServer::actingAs($user)
        ->tool(ListChecklists::class)
        ->assertOk()
        ->assertDontSee('Secret List')
        ->assertStructuredContent(['count' => 0, 'checklists' => []]);
});
