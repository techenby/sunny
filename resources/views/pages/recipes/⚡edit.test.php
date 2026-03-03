<?php

use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('can view edit page', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Oden']);

    $this->actingAs($user)
        ->get(route('recipes.edit', ['recipe' => $recipe]))
        ->assertOk()
        ->assertSee('Oden');
});

test('can edit a recipe', function () {
    $user = User::factory()->withTeam()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Old Name']);

    Livewire::actingAs($user)
        ->test('pages::recipes.edit', ['recipe' => $recipe])
        ->assertSet('form.name', 'Old Name')
        ->set('form.name', 'New Name')
        ->set('form.description', 'A delicious recipe')
        ->call('save')
        ->assertHasNoErrors()
        ->assertRedirect(route('recipes.show', $recipe));

    expect($recipe->fresh())
        ->name->toBe('New Name')
        ->description->toBe('A delicious recipe');
});

test('cannot edit a recipe from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherRecipe = Recipe::factory()->for(Team::factory())->create();

    $this->actingAs($user)
        ->get(route('recipes.edit', $otherRecipe))
        ->assertForbidden();
});
