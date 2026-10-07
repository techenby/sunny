<?php

use App\Mcp\Tools\Recipes\UpdateRecipeSharing;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('it enables sharing and returns the public url', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake', 'share_token' => null]);

    $response = SunnyTestServer::actingAs($user)
        ->tool(UpdateRecipeSharing::class, ['id' => $recipe->id, 'shared' => true]);

    $recipe->refresh();

    expect($recipe->isShared())->toBeTrue();

    $response->assertOk()
        ->assertStructuredContent([
            'id' => $recipe->id,
            'name' => 'Chocolate Cake',
            'shared' => true,
            'share_url' => route('recipes.shared', $recipe->share_token),
        ]);
});

test('it keeps the existing link when sharing is already enabled', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['share_token' => 'existing-token']);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateRecipeSharing::class, ['id' => $recipe->id, 'shared' => true])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('shared', true)
            ->where('share_url', route('recipes.shared', 'existing-token'))
            ->etc());

    expect($recipe->fresh()->share_token)->toBe('existing-token');
});

test('it disables sharing', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['share_token' => 'existing-token']);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateRecipeSharing::class, ['id' => $recipe->id, 'shared' => false])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('shared', false)
            ->where('share_url', null)
            ->etc());

    expect($recipe->fresh()->isShared())->toBeFalse();
});

test('it requires shared', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    SunnyTestServer::actingAs($user)
        ->tool(UpdateRecipeSharing::class, ['id' => $recipe->id])
        ->assertHasErrors(['Set shared to true']);
});

test('it cannot share recipes from other teams', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['share_token' => null]);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateRecipeSharing::class, ['id' => $recipe->id, 'shared' => true])
        ->assertHasErrors(['Recipe not found.']);

    expect($recipe->fresh()->isShared())->toBeFalse();
});
