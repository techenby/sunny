<?php

use App\Enums\TeamRole;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests cannot open or save shared recipes', function () {
    $this->getJson(route('api.shared-recipes.show', 'abc'))->assertUnauthorized();
    $this->postJson(route('api.shared-recipes.save', 'abc'))->assertUnauthorized();
});

test('show returns a shared recipe from another team', function () {
    Storage::fake();
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'name' => 'Grandma’s Pie',
        'share_token' => 'abc',
        'photo_path' => 'teams/1/recipes/pie.png',
        'client_uuid' => '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d',
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('api.shared-recipes.show', 'abc'))
        ->assertOk()
        ->assertJsonPath('data.id', $recipe->id)
        ->assertJsonPath('data.team_id', $recipe->team_id)
        ->assertJsonPath('data.name', 'Grandma’s Pie')
        ->assertJsonPath('data.share_url', route('recipes.shared', 'abc'))
        ->assertJsonMissingPath('data.photo_path')
        ->assertJsonMissingPath('data.client_uuid');

    expect($response->json('data.photo_url'))->toContain('pie.png');
});

test('show returns 404 when the share link is turned off or unknown', function () {
    $user = User::factory()->create();
    Recipe::factory()->create(['share_token' => 'abc'])->disableSharing();

    $this->actingAs($user)->getJson(route('api.shared-recipes.show', 'abc'))->assertNotFound();
    $this->actingAs($user)->getJson(route('api.shared-recipes.show', 'unknown'))->assertNotFound();
});

test('save adds a shared recipe to a team of the user with its tags and photo', function () {
    Storage::fake();
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create([
        'name' => 'Grandma’s Pie',
        'share_token' => 'abc',
        'tags' => ['Dessert'],
        'photo_path' => 'teams/1/recipes/pie.png',
    ]);
    Storage::put($recipe->photo_path, 'pie photo');

    $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $user->current_team_id])
        ->assertCreated()
        ->assertJsonPath('data.team_id', $user->current_team_id)
        ->assertJsonPath('data.name', 'Grandma’s Pie')
        ->assertJsonPath('data.source', route('recipes.shared', 'abc'))
        ->assertJsonPath('data.share_token', null)
        ->assertJsonPath('data.tags', ['Dessert']);

    $saved = $user->currentTeam->recipes()->sole();

    expect($saved->photo_path)->toStartWith("teams/{$user->current_team_id}/recipes/grandmas-pie-")
        ->and(Storage::get($saved->photo_path))->toBe('pie photo')
        ->and($recipe->fresh()->photo_path)->toBe('teams/1/recipes/pie.png')
        ->and($recipe->fresh()->team_id)->not->toBe($user->current_team_id);
});

test('save returns the recipe already saved from the link', function () {
    $user = User::factory()->create();
    Recipe::factory()->create(['share_token' => 'abc']);

    $first = $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $user->current_team_id])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $user->current_team_id])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect($user->currentTeam->recipes()->count())->toBe(1);
});

test('save returns the existing recipe when a client uuid is retried', function () {
    $user = User::factory()->create();
    Recipe::factory()->create(['share_token' => 'abc']);
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';

    $first = $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $user->current_team_id, 'client_uuid' => $uuid])
        ->assertCreated()
        ->assertJsonPath('data.client_uuid', $uuid);

    $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $user->current_team_id, 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));
});

test('save returns the existing recipe when a retry wins the race to insert it', function () {
    $user = User::factory()->create();
    $shared = Recipe::factory()->create(['share_token' => 'abc']);
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $raced = createAfterLookup('recipes', 'client_uuid', fn () => Recipe::factory()->for($user->currentTeam)->create(['name' => $shared->name, 'client_uuid' => $uuid]));

    $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $user->current_team_id, 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $raced->model->id);

    expect($user->currentTeam->recipes()->count())->toBe(1);
});

test('save returns the original when the team already owns the recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['share_token' => 'abc']);

    $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $user->current_team_id])
        ->assertOk()
        ->assertJsonPath('data.id', $recipe->id);

    expect($user->currentTeam->recipes()->count())->toBe(1);
});

test('save adds the recipe to the team given, not the current team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    Recipe::factory()->create(['share_token' => 'abc']);

    $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $team->id])
        ->assertCreated()
        ->assertJsonPath('data.team_id', $team->id);

    expect($user->currentTeam->recipes()->count())->toBe(0);
});

test('save validates the team', function (Closure $teamId) {
    $user = User::factory()->create();
    Recipe::factory()->create(['share_token' => 'abc']);

    $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $teamId(), 'client_uuid' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id', 'client_uuid']);

    expect(Recipe::count())->toBe(1);
})->with([
    'missing' => fn () => fn () => null,
    'a team the user does not belong to' => fn () => fn () => Team::factory()->create()->id,
]);

test('save returns 404 when the share link is turned off', function () {
    $user = User::factory()->create();
    Recipe::factory()->create(['share_token' => 'abc'])->disableSharing();

    $this->actingAs($user)
        ->postJson(route('api.shared-recipes.save', 'abc'), ['team_id' => $user->current_team_id])
        ->assertNotFound();

    expect($user->currentTeam->recipes()->count())->toBe(0);
});
