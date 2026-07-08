<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Recipes\UpdateRecipe;
use App\Models\Recipe;
use App\Models\User;

test('it updates a recipe on the current team', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);

    SunnyServer::actingAs($user)
        ->tool(UpdateRecipe::class, [
            'id' => $recipe->id,
            'name' => 'Double Chocolate Cake',
            'servings' => '10',
        ])
        ->assertOk()
        ->assertSee('Double Chocolate Cake');

    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'name' => 'Double Chocolate Cake',
        'servings' => '10',
    ]);
});

test('it wraps plain text ingredients and instructions in html lists', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateRecipe::class, [
            'id' => $recipe->id,
            'ingredients' => "2 cups flour\n1 cup water",
            'instructions' => "* Mix everything.\n* Bake at 450F.",
        ])
        ->assertOk();

    expect($recipe->fresh())
        ->ingredients->toBe('<ul><li>2 cups flour</li><li>1 cup water</li></ul>')
        ->instructions->toBe('<ol><li>Mix everything.</li><li>Bake at 450F.</li></ol>');
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateRecipe::class, ['name' => 'New Name'])
        ->assertHasErrors();
});

test('it cannot update recipes from other teams', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['name' => 'Secret Family Recipe']);

    SunnyServer::actingAs($user)
        ->tool(UpdateRecipe::class, ['id' => $recipe->id, 'name' => 'Stolen Recipe'])
        ->assertHasErrors(['Recipe not found.']);

    expect($recipe->fresh()->name)->toBe('Secret Family Recipe');
});
