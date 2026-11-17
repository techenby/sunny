<?php

use App\Models\Recipe;
use App\Models\User;

test('guests cannot change recipe sharing', function () {
    $this->putJson(route('api.recipes.share.update', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.recipes.share.destroy', ['household', 1]))->assertUnauthorized();
});

test('update turns on the share link and returns its url', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['share_token' => null]);

    $response = $this->actingAs($user)
        ->putJson(route('api.recipes.share.update', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonPath('data.id', $recipe->id);

    $token = $recipe->fresh()->share_token;

    expect($token)->not->toBeNull()
        ->and($response->json('data.share_token'))->toBe($token)
        ->and($response->json('data.share_url'))->toBe(route('recipes.shared', $token));
});

test('update keeps the existing share link', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['share_token' => 'abc']);

    $this->actingAs($user)
        ->putJson(route('api.recipes.share.update', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonPath('data.share_url', route('recipes.shared', 'abc'));

    expect($recipe->fresh()->share_token)->toBe('abc');
});

test('destroy turns off the share link', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['share_token' => 'abc']);

    $this->actingAs($user)
        ->deleteJson(route('api.recipes.share.destroy', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonPath('data.share_token', null)
        ->assertJsonPath('data.share_url', null);

    expect($recipe->fresh()->isShared())->toBeFalse();
});

test('sharing returns 403 for a team the user does not belong to recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create(['share_token' => 'abc']);

    $this->actingAs($user)
        ->putJson(route('api.recipes.share.update', [$recipe->team, $recipe]))
        ->assertForbidden();

    $this->actingAs($user)
        ->deleteJson(route('api.recipes.share.destroy', [$recipe->team, $recipe]))
        ->assertForbidden();

    expect($recipe->fresh()->share_token)->toBe('abc');
});
