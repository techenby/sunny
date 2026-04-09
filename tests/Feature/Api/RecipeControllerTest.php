<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests cannot access recipes', function () {
    $this->getJson(route('api.recipes.index'))->assertUnauthorized();
    $this->postJson(route('api.recipes.store'))->assertUnauthorized();
    $this->getJson(route('api.recipes.show', 1))->assertUnauthorized();
    $this->patchJson(route('api.recipes.update', 1))->assertUnauthorized();
    $this->deleteJson(route('api.recipes.destroy', 1))->assertUnauthorized();
});

test('index returns recipes for the current team', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->count(3)->create();
    Recipe::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
});

test('store creates a recipe and returns it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store'), [
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
        ->postJson(route('api.recipes.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('show returns a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', $recipe))
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
        ->getJson(route('api.recipes.show', $recipe))
        ->assertOk()
        ->assertJsonMissingPath('data.photo_path');

    expect($response->json('data.photo_url'))->toBeString()->toContain('cake.png');
});

test('show returns null photo_url when recipe has no photo', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['photo_path' => null]);

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', $recipe))
        ->assertOk()
        ->assertJsonPath('data.photo_url', null);
});

test('show returns 403 for another team recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', $recipe))
        ->assertForbidden();
});

test('update modifies a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patchJson(route('api.recipes.update', $recipe), [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');

    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'name' => 'Updated Name',
    ]);
});

test('update returns 403 for another team recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.recipes.update', $recipe), ['name' => 'Nope'])
        ->assertForbidden();
});

test('destroy deletes a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.recipes.destroy', $recipe))
        ->assertNoContent();

    $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
});

test('destroy returns 403 for another team recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.recipes.destroy', $recipe))
        ->assertForbidden();
});
