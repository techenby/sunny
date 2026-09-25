<?php

use App\Enums\TeamRole;
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
        ->assertJsonStructure(['teams', 'recipes', 'items', 'synced_at']);
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
