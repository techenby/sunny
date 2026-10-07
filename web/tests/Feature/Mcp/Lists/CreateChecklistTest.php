<?php

use App\Enums\ChecklistType;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\CreateChecklist;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it creates a household checklist with items', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateChecklist::class, [
            'name' => 'Groceries',
            'type' => 'shopping',
            'items' => ['Milk', 'Eggs'],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('name', 'Groceries')
            ->where('type', 'shopping')
            ->where('user_id', null)
            ->where('owner_name', null)
            ->where('item_count', 2)
            ->where('completed_count', 0)
            ->where('progress', 0)
            ->has('items', 2)
            ->where('items.0.name', 'Milk')
            ->where('items.0.position', 1)
            ->where('items.0.completed', false)
            ->where('items.1.name', 'Eggs')
            ->where('items.1.position', 2)
            ->etc());

    $checklist = $user->currentTeam->checklists()->sole();

    expect($checklist->name)->toBe('Groceries')
        ->and($checklist->type)->toBe(ChecklistType::Shopping)
        ->and($checklist->user_id)->toBeNull()
        ->and($checklist->items->pluck('name')->all())->toBe(['Milk', 'Eggs']);
});

test('it creates a checklist owned by a team member', function () {
    $user = User::factory()->create(['name' => 'Andy']);

    SunnyServer::actingAs($user)
        ->tool(CreateChecklist::class, [
            'name' => 'Chores',
            'type' => 'todo',
            'user_id' => $user->id,
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('user_id', $user->id)
            ->where('owner_name', 'Andy')
            ->where('items', [])
            ->etc());

    expect($user->currentTeam->checklists()->sole()->user_id)->toBe($user->id);
});

test('it requires a name and a valid type', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateChecklist::class, ['type' => 'groceries'])
        ->assertHasErrors([
            'The name field is required.',
            'The type must be one of: todo, shopping, wishlist.',
        ]);

    expect($user->currentTeam->checklists()->count())->toBe(0);
});

test('it rejects an owner who is not on the current team', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateChecklist::class, [
            'name' => 'Chores',
            'type' => 'todo',
            'user_id' => $stranger->id,
        ])
        ->assertHasErrors(['The user_id must be the id of a member of the current team, or null for a household list.']);

    expect($user->currentTeam->checklists()->count())->toBe(0);
});

test('it rejects empty item names', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateChecklist::class, [
            'name' => 'Groceries',
            'type' => 'shopping',
            'items' => ['Milk', ''],
        ])
        ->assertHasErrors(['Each item must be a non-empty name.']);

    expect($user->currentTeam->checklists()->count())->toBe(0);
});
