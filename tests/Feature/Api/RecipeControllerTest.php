<?php

use App\Enums\TeamRole;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests cannot access recipes', function () {
    $this->getJson(route('api.recipes.index', 'household'))->assertUnauthorized();
    $this->postJson(route('api.recipes.store', 'household'))->assertUnauthorized();
    $this->getJson(route('api.recipes.show', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.recipes.update', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.recipes.destroy', ['household', 1]))->assertUnauthorized();
});

test('index returns recipes for the current team', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->count(3)->create();
    Recipe::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
});

test('store creates a recipe and returns it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), [
            'name' => 'Chocolate Cake',
            'servings' => '8',
            'prep_time' => '20 minutes',
            'cook_time' => '45 minutes',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Chocolate Cake')
        ->assertJsonPath('data.servings', '8');

    $this->assertDatabaseHas('recipes', [
        'team_id' => $user->current_team_id,
        'name' => 'Chocolate Cake',
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('show returns a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonPath('data.id', $recipe->id)
        ->assertJsonPath('data.name', $recipe->name);
});

test('show returns photo_url when recipe has a photo', function () {
    Storage::fake();

    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'photo_path' => "teams/{$user->current_team_id}/recipes/cake.png",
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonMissingPath('data.photo_path');

    expect($response->json('data.photo_url'))->toBeString()->toContain('cake.png');
});

test('show returns null photo_url when recipe has no photo', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['photo_path' => null]);

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonPath('data.photo_url', null);
});

test('show returns 403 for a team the user does not belong to recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$recipe->team, $recipe]))
        ->assertForbidden();
});

test('update modifies a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patchJson(route('api.recipes.update', [$user->currentTeam, $recipe]), [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');

    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'name' => 'Updated Name',
    ]);
});

test('update returns 403 for a team the user does not belong to recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.recipes.update', [$recipe->team, $recipe]), ['name' => 'Nope'])
        ->assertForbidden();
});

test('destroy deletes a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.recipes.destroy', [$user->currentTeam, $recipe]))
        ->assertNoContent();

    $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
});

test('destroy returns 403 for a team the user does not belong to recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.recipes.destroy', [$recipe->team, $recipe]))
        ->assertForbidden();
});

test('index returns recipes for another team the user belongs to without switching teams', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    Recipe::factory()->for($team)->count(2)->create();
    Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.index', $team))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($user->fresh()->current_team_id)->not->toBe($team->id);
});

test('store creates the recipe in the team from the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $team), ['name' => 'Pancakes'])
        ->assertCreated();

    expect($team->recipes()->count())->toBe(1)
        ->and($user->currentTeam->recipes()->count())->toBe(0);
});

test('show returns 404 when the recipe belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $recipe = Recipe::factory()->for($team)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$user->currentTeam, $recipe]))
        ->assertNotFound();
});

test('store rejects a parent from another team', function () {
    $user = User::factory()->create();
    $parent = Recipe::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Pancakes', 'parent_id' => $parent->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});
