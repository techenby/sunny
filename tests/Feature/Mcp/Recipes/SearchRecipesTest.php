<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Recipes\SearchRecipes;
use App\Models\Recipe;
use App\Models\User;

test('it returns recipes for the current team', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);
    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Banana Bread']);

    SunnyServer::actingAs($user)
        ->tool(SearchRecipes::class)
        ->assertOk()
        ->assertSee('Chocolate Cake')
        ->assertSee('Banana Bread')
        ->assertSee('get-recipe');
});

test('it filters recipes by name or ingredients', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);
    Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Morning Oats',
        'ingredients' => '<ul><li>2 cups oats</li><li>1 cup chocolate chips</li></ul>',
    ]);
    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Garden Salad']);

    SunnyServer::actingAs($user)
        ->tool(SearchRecipes::class, ['query' => 'chocolate'])
        ->assertOk()
        ->assertSee('Chocolate Cake')
        ->assertSee('Morning Oats')
        ->assertDontSee('Garden Salad');
});

test('it filters recipes by tag', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->withTags(['Dinner'])->create(['name' => 'Pot Roast']);
    Recipe::factory()->for($user->currentTeam)->withTags(['Dessert'])->create(['name' => 'Apple Pie']);

    SunnyServer::actingAs($user)
        ->tool(SearchRecipes::class, ['tag' => 'Dinner'])
        ->assertOk()
        ->assertSee('Pot Roast')
        ->assertDontSee('Apple Pie');
});

test('it respects the limit argument', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->count(3)->create();

    SunnyServer::actingAs($user)
        ->tool(SearchRecipes::class, ['limit' => 2])
        ->assertOk()
        ->assertSee('"count": 2');
});

test('it validates the limit argument', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(SearchRecipes::class, ['limit' => 500])
        ->assertHasErrors();
});

test('it does not return recipes from other teams', function () {
    $user = User::factory()->create();
    Recipe::factory()->create(['name' => 'Secret Family Recipe']);

    SunnyServer::actingAs($user)
        ->tool(SearchRecipes::class)
        ->assertOk()
        ->assertDontSee('Secret Family Recipe');
});
