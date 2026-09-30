<?php

use App\Enums\TeamRole;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;

test('guests cannot access sync', function () {
    $this->getJson(route('api.sync'))->assertUnauthorized();
});

test('returns all teams, recipes, and items for the user', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->count(2)->create();
    Item::factory()->for($user->currentTeam)->count(3)->create();

    Recipe::factory()->count(2)->create();
    Item::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'teams')
        ->assertJsonCount(2, 'recipes')
        ->assertJsonCount(3, 'items')
        ->assertJsonStructure(['teams', 'recipes', 'items', 'checklists', 'checklist_items', 'synced_at']);
});

test('returns checklists and their items for the user', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    ChecklistItem::factory()->for($checklist)->count(2)->create();

    ChecklistItem::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'checklists')
        ->assertJsonCount(2, 'checklist_items')
        ->assertJsonPath('checklists.0.id', $checklist->id)
        ->assertJsonPath('checklist_items.0.checklist_id', $checklist->id);
});

test('includes soft-deleted checklists but not their items', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    ChecklistItem::factory()->for($checklist)->create();
    $checklist->delete();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'checklists')
        ->assertJsonPath('checklists.0.deleted_at', fn ($value) => $value !== null)
        ->assertJsonCount(0, 'checklist_items');
});

test('returns only checklist items updated since the given timestamp', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create(['updated_at' => now()->subDays(2)]);
    ChecklistItem::factory()->for($checklist)->create(['updated_at' => now()->subDays(2)]);
    $new = ChecklistItem::factory()->for($checklist)->create(['updated_at' => now()->subHour()]);

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertOk()
        ->assertJsonCount(0, 'checklists')
        ->assertJsonCount(1, 'checklist_items')
        ->assertJsonPath('checklist_items.0.id', $new->id);
});

test('returns only records updated since the given timestamp', function () {
    $user = User::factory()->create();

    [$old, $new] = Recipe::factory()
        ->for($user->currentTeam)
        ->count(2)
        ->sequence(
            ['updated_at' => now()->subDays(2)],
            ['updated_at' => now()->subHour()]
        )
        ->create();

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertOk()
        ->assertJsonCount(1, 'recipes')
        ->assertJsonPath('recipes.0.id', $new->id);
});

test('includes soft-deleted records', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();
    $recipe->delete();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'recipes')
        ->assertJsonPath('recipes.0.id', $recipe->id)
        ->assertJsonPath('recipes.0.deleted_at', fn ($value) => $value !== null);
});

test('includes data from all user teams', function () {
    $user = User::factory()->create();
    $secondTeam = Team::factory()->create();
    $user->teams()->attach($secondTeam, ['role' => TeamRole::Member]);

    Recipe::factory()->for($user->currentTeam)->create();
    Recipe::factory()->for($secondTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(2, 'teams')
        ->assertJsonCount(2, 'recipes');
});

test('validates since parameter is a valid date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => 'not-a-date']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('since');
});
