<?php

use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

test('can view a recipe', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Pasta Carbonara',
        'ingredients' => 'Pasta, Eggs, Bacon',
        'instructions' => 'Cook pasta, mix with eggs and bacon',
    ]);

    $this->actingAs($user)
        ->get(route('recipes.show', $recipe))
        ->assertOk()
        ->assertSee('Pasta Carbonara')
        ->assertSee('Pasta, Eggs, Bacon');
});

test('cannot view a recipe from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherRecipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->get(route('recipes.show', $otherRecipe))
        ->assertForbidden();
});

test('can create a remix of a recipe', function () {
    $user = User::factory()->withTeam()->create();
    $original = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Original Chocolate Cake',
    ]);

    Livewire::actingAs($user)
        ->test('pages::recipes.show', ['recipe' => $original])
        ->assertSee('Original Chocolate Cake')
        ->call('remix')
        ->assertRedirect();

    $remix = Recipe::where('name', 'Original Chocolate Cake (Remix)')->first();

    expect($remix)->not->toBeNull()
        ->parent_id->toBe($original->id)
        ->team_id->toBe($user->current_team_id);
});

test('remix shows parent recipe', function () {
    $user = User::factory()->withTeam()->create();
    $original = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Original Recipe']);
    $remix = Recipe::factory()->for($user->currentTeam)->remixOf($original)->create(['name' => 'Remixed Recipe']);

    $this->actingAs($user)
        ->get(route('recipes.show', $remix))
        ->assertOk()
        ->assertSee('Remixed From')
        ->assertSee('Original Recipe');
});

test('parent shows remixes', function () {
    $user = User::factory()->withTeam()->create();
    $original = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Original Recipe']);
    Recipe::factory()->for($user->currentTeam)->remixOf($original)->create(['name' => 'Remixed Recipe']);

    $this->actingAs($user)
        ->get(route('recipes.show', $original))
        ->assertOk()
        ->assertSee('Remixes')
        ->assertSee('Remixed Recipe');
});

test('owner can toggle sharing on recipe show page', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->create(['team_id' => $user->currentTeam->id]);

    $this->actingAs($user);

    Livewire::test('pages::recipes.show', ['recipe' => $recipe])
        ->call('toggleSharing');

    expect($recipe->fresh()->isShared())->toBeTrue();

    Livewire::test('pages::recipes.show', ['recipe' => $recipe])
        ->call('toggleSharing');

    expect($recipe->fresh()->isShared())->toBeFalse();
});

test('non-owner cannot toggle sharing', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user);

    Livewire::test('pages::recipes.show', ['recipe' => $recipe])
        ->call('toggleSharing')
        ->assertForbidden();
});
