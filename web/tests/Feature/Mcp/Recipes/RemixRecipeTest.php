<?php

use App\Mcp\Tools\Recipes\RemixRecipe;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('it remixes a recipe on the current team', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);

    SunnyTestServer::actingAs($user)
        ->tool(RemixRecipe::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', fn (int $id) => $id !== $recipe->id)
            ->where('name', 'Chocolate Cake (Remix)')
            ->where('slug', 'chocolate-cake-remix')
            ->where('parent_id', $recipe->id));

    expect($user->currentTeam->recipes()->where('parent_id', $recipe->id)->count())->toBe(1);
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(RemixRecipe::class)
        ->assertHasErrors();
});

test('it cannot remix recipes from other teams', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(RemixRecipe::class, ['id' => $recipe->id])
        ->assertHasErrors(['Recipe not found.']);

    expect(Recipe::query()->count())->toBe(1);
});
