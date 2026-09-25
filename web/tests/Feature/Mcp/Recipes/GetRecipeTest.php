<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Recipes\GetRecipe;
use App\Models\Recipe;
use App\Models\User;

test('it returns a recipe by id', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Chocolate Cake',
        'ingredients' => '<ul><li>2 cups flour</li></ul>',
        'instructions' => '<ol><li>Mix and bake.</li></ol>',
        'notes' => 'Best served warm.',
        'nutrition' => 'Calories: 350 kcal',
    ]);

    SunnyServer::actingAs($user)
        ->tool(GetRecipe::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertSee('Chocolate Cake')
        ->assertSee('2 cups flour')
        ->assertSee('Mix and bake.')
        ->assertSee('Best served warm.')
        ->assertSee('Calories: 350 kcal');
});

test('it returns a recipe by slug', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Banana Bread']);

    SunnyServer::actingAs($user)
        ->tool(GetRecipe::class, ['slug' => $recipe->slug])
        ->assertOk()
        ->assertSee('Banana Bread');
});

test('it requires an id or a slug', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetRecipe::class)
        ->assertHasErrors();
});

test('it does not return recipes from other teams', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetRecipe::class, ['id' => $recipe->id])
        ->assertHasErrors(['Recipe not found.']);
});
