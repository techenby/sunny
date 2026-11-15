<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Recipes\CreateRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Arr;

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

test('it stores plain text ingredients and instructions as rows', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateRecipe::class, [
            'name' => 'Simple Bread',
            'ingredients' => "# Dough\n- 2 cups flour\n- 1 cup water\n\n- 1 tsp salt",
            'instructions' => "1. Mix everything.\n2. Bake at 450F.",
        ])
        ->assertOk();

    $recipe = Recipe::sole();

    expect(array_map(fn (array $row): array => Arr::only($row, ['type', 'text']), $recipe->ingredients))->toBe([
        ['type' => 'heading', 'text' => 'Dough'],
        ['type' => 'item', 'text' => '2 cups flour'],
        ['type' => 'item', 'text' => '1 cup water'],
        ['type' => 'item', 'text' => '1 tsp salt'],
    ])->and(array_column($recipe->instructions, 'text'))->toBe(['Mix everything.', 'Bake at 450F.']);
});

test('it converts html ingredients and instructions to rows', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateRecipe::class, [
            'name' => 'Fancy Bread',
            'ingredients' => '<ul><li>2 cups flour</li></ul>',
            'instructions' => '<ol><li>Mix and bake.</li></ol>',
        ])
        ->assertOk();

    $recipe = Recipe::sole();

    expect(array_column($recipe->ingredients, 'text'))->toBe(['2 cups flour'])
        ->and(array_column($recipe->instructions, 'text'))->toBe(['Mix and bake.']);
});

test('it requires a name', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateRecipe::class, ['servings' => '4'])
        ->assertHasErrors();

    expect(Recipe::count())->toBe(0);
});
