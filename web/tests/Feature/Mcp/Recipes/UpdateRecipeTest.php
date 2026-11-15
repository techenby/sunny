<?php

use App\Mcp\Tools\Recipes\UpdateRecipe;
use App\Models\Recipe;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it updates a recipe on the current team', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);

    SunnyTestServer::actingAs($user)
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

test('it stores plain text ingredients and instructions as rows', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    SunnyTestServer::actingAs($user)
        ->tool(UpdateRecipe::class, [
            'id' => $recipe->id,
            'ingredients' => "2 cups flour\n1 cup water",
            'instructions' => "* Mix everything.\n* Bake at 450F.",
        ])
        ->assertOk();

    expect(array_column($recipe->fresh()->ingredients, 'text'))->toBe(['2 cups flour', '1 cup water'])
        ->and(array_column($recipe->fresh()->instructions, 'text'))->toBe(['Mix everything.', 'Bake at 450F.']);
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(UpdateRecipe::class, ['name' => 'New Name'])
        ->assertHasErrors();
});

test('it cannot update recipes from other teams', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['name' => 'Secret Family Recipe']);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateRecipe::class, ['id' => $recipe->id, 'name' => 'Stolen Recipe'])
        ->assertHasErrors(['Recipe not found.']);

    expect($recipe->fresh()->name)->toBe('Secret Family Recipe');
});
