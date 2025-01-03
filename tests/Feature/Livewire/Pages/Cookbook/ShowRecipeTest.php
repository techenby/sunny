<?php

use App\Livewire\Pages\Cookbook\ShowRecipe;
use App\Models\Recipe;
use App\Models\User;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    $this->actingAs($user)
        ->get('/cookbook/recipes/' . $recipe->id)
        ->assertOk()
        ->assertSee('Oden')
        ->assertSee('Edit');
});

test('can view page as guest', function () {
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    $this->get('/cookbook/recipes/' . $recipe->id)
        ->assertOk()
        ->assertSee('Oden')
        ->assertDontSee('Edit');
});

test('can view component', function () {
    $recipe = Recipe::factory()->create(['name' => 'Oden']);

    Livewire::test(ShowRecipe::class, ['recipe' => $recipe])
        ->assertOk()
        ->assertSee('Oden');
});
