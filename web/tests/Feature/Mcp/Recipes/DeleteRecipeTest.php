<?php

use App\Mcp\Tools\Recipes\DeleteRecipe;
use App\Models\Recipe;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it deletes a recipe on the current team', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake']);

    SunnyTestServer::actingAs($user)
        ->tool(DeleteRecipe::class, ['id' => $recipe->id])
        ->assertOk()
        ->assertSee('Chocolate Cake');

    expect($recipe->fresh())->toBeTrashed();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteRecipe::class)
        ->assertHasErrors();
});

test('it cannot delete recipes from other teams', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteRecipe::class, ['id' => $recipe->id])
        ->assertHasErrors(['Recipe not found.']);

    expect($recipe->fresh()->trashed())->toBeFalse();
});
