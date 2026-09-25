<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Recipes\CreateRecipe;
use App\Models\Recipe;
use App\Models\User;

test('it creates a recipe for the current team', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateRecipe::class, [
            'name' => 'Chocolate Cake',
            'servings' => '8',
            'prep_time' => '20 minutes',
            'cook_time' => '45 minutes',
            'tags' => ['Dessert'],
        ])
        ->assertOk()
        ->assertSee('Chocolate Cake');

    $this->assertDatabaseHas('recipes', [
        'team_id' => $user->current_team_id,
        'name' => 'Chocolate Cake',
        'servings' => '8',
    ]);

    expect(Recipe::sole()->tags)->toBe(['Dessert']);
});

test('it wraps plain text ingredients and instructions in html lists', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateRecipe::class, [
            'name' => 'Simple Bread',
            'ingredients' => "- 2 cups flour\n- 1 cup water\n\n- 1 tsp salt",
            'instructions' => "1. Mix everything.\n2. Bake at 450F.",
        ])
        ->assertOk();

    expect(Recipe::sole())
        ->ingredients->toBe('<ul><li>2 cups flour</li><li>1 cup water</li><li>1 tsp salt</li></ul>')
        ->instructions->toBe('<ol><li>Mix everything.</li><li>Bake at 450F.</li></ol>');
});

test('it leaves html ingredients and instructions untouched', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateRecipe::class, [
            'name' => 'Fancy Bread',
            'ingredients' => '<ul><li>2 cups flour</li></ul>',
            'instructions' => '<ol><li>Mix and bake.</li></ol>',
        ])
        ->assertOk();

    expect(Recipe::sole())
        ->ingredients->toBe('<ul><li>2 cups flour</li></ul>')
        ->instructions->toBe('<ol><li>Mix and bake.</li></ol>');
});

test('it requires a name', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateRecipe::class, ['servings' => '4'])
        ->assertHasErrors();

    expect(Recipe::count())->toBe(0);
});
