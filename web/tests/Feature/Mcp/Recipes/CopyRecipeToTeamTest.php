<?php

use App\Enums\TeamRole;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Recipes\CopyRecipeToTeam;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it copies a recipe to another team of the user', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create(['name' => 'Grandparents']);
    $user->teams()->attach($otherTeam, ['role' => TeamRole::Member]);
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake', 'share_token' => 'abc']);

    SunnyServer::actingAs($user)
        ->tool(CopyRecipeToTeam::class, ['id' => $recipe->id, 'team_id' => $otherTeam->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', fn (int $id) => $id !== $recipe->id)
            ->where('name', 'Chocolate Cake')
            ->where('slug', 'chocolate-cake')
            ->where('parent_id', $recipe->id)
            ->where('team.id', $otherTeam->id)
            ->where('team.name', 'Grandparents'));

    $copy = $otherTeam->recipes()->sole();

    expect($copy->share_token)->toBeNull()
        ->and($recipe->fresh()->team_id)->toBe($user->current_team_id);
});

test('it requires an id and team_id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CopyRecipeToTeam::class)
        ->assertHasErrors(['id', 'team id']);
});

test('it cannot copy a recipe to a team the user does not belong to', function () {
    $user = User::factory()->create();
    $strangerTeam = Team::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(CopyRecipeToTeam::class, ['id' => $recipe->id, 'team_id' => $strangerTeam->id])
        ->assertHasErrors(['Team not found.']);

    expect($strangerTeam->recipes()->count())->toBe(0);
});

test('it cannot copy a recipe to the current team', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(CopyRecipeToTeam::class, ['id' => $recipe->id, 'team_id' => $user->current_team_id])
        ->assertHasErrors(['Team not found.']);

    expect(Recipe::query()->count())->toBe(1);
});

test('it cannot copy recipes from other teams', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($otherTeam, ['role' => TeamRole::Member]);
    $recipe = Recipe::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CopyRecipeToTeam::class, ['id' => $recipe->id, 'team_id' => $otherTeam->id])
        ->assertHasErrors(['Recipe not found.']);

    expect($otherTeam->recipes()->count())->toBe(0);
});
