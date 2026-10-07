<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Recipes\GetRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

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
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $recipe->id)
            ->where('name', 'Chocolate Cake')
            ->where('ingredients', '<ul><li>2 cups flour</li></ul>')
            ->where('instructions', '<ol><li>Mix and bake.</li></ol>')
            ->where('notes', 'Best served warm.')
            ->where('nutrition', 'Calories: 350 kcal')
            ->etc());
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
