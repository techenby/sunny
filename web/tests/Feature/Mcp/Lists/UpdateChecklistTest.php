<?php

use App\Enums\ChecklistType;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\UpdateChecklist;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it updates the provided fields', function () {
    $user = User::factory()->create(['name' => 'Andy']);
    $checklist = Checklist::factory()->todo()->for($user->currentTeam)->create(['name' => 'Chores']);
    ChecklistItem::factory()->for($checklist)->create(['name' => 'Vacuum']);

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklist::class, [
            'id' => $checklist->id,
            'name' => 'Weekend Chores',
            'user_id' => $user->id,
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $checklist->id)
            ->where('name', 'Weekend Chores')
            ->where('type', 'todo')
            ->where('user_id', $user->id)
            ->where('owner_name', 'Andy')
            ->where('items.0.name', 'Vacuum')
            ->etc());

    $checklist->refresh();

    expect($checklist->name)->toBe('Weekend Chores')
        ->and($checklist->type)->toBe(ChecklistType::Todo)
        ->and($checklist->user_id)->toBe($user->id);
});

test('it makes a checklist a household list when user_id is null', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->ownedBy($user)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklist::class, ['id' => $checklist->id, 'user_id' => null, 'type' => 'wishlist'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('user_id', null)
            ->where('type', 'wishlist')
            ->etc());

    $checklist->refresh();

    expect($checklist->user_id)->toBeNull()
        ->and($checklist->type)->toBe(ChecklistType::Wishlist);
});

test('it requires at least one field to update', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklist::class, ['id' => $checklist->id])
        ->assertHasErrors(['Provide at least one of name, type, or user_id to update.']);
});

test('it validates the fields', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Chores']);

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklist::class, ['id' => $checklist->id, 'type' => 'groceries', 'user_id' => $stranger->id])
        ->assertHasErrors([
            'The type must be one of: todo, shopping, wishlist.',
            'The user_id must be the id of a member of the current team, or null for a household list.',
        ]);

    expect($checklist->refresh()->user_id)->toBeNull();
});

test('it does not update checklists from other teams', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create(['name' => 'Secret List']);

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklist::class, ['id' => $checklist->id, 'name' => 'Hijacked'])
        ->assertHasErrors(['Checklist not found.']);

    expect($checklist->refresh()->name)->toBe('Secret List');
});
