<?php

use App\Enums\TeamRole;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('guests cannot access recipes', function () {
    $this->getJson(route('api.recipes.index', 'household'))->assertUnauthorized();
    $this->postJson(route('api.recipes.store', 'household'))->assertUnauthorized();
    $this->getJson(route('api.recipes.show', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.recipes.update', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.recipes.destroy', ['household', 1]))->assertUnauthorized();
    $this->postJson(route('api.recipes.copy', ['household', 1]))->assertUnauthorized();
});

test('index returns recipes for the current team', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->count(3)->create();
    Recipe::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'slug']]]);
});

test('store creates a recipe and returns it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), [
            'name' => 'Chocolate Cake',
            'servings' => '8',
            'prep_time' => '20 minutes',
            'cook_time' => '45 minutes',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Chocolate Cake')
        ->assertJsonPath('data.servings', '8');

    $this->assertDatabaseHas('recipes', [
        'team_id' => $user->current_team_id,
        'name' => 'Chocolate Cake',
    ]);
});

test('store, update and copy give a recipe named like one the team deleted its own slug', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    Recipe::factory()->for($user->currentTeam)->create(['name' => 'Chocolate Cake'])->delete();
    Recipe::factory()->for($team)->create(['name' => 'Chocolate Cake'])->delete();
    $soup = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Soup']);

    $stored = $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Chocolate Cake'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'chocolate-cake-1');

    $this->actingAs($user)
        ->patchJson(route('api.recipes.update', [$user->currentTeam, $soup]), ['name' => 'Chocolate Cake'])
        ->assertOk()
        ->assertJsonPath('data.slug', 'chocolate-cake-2');

    $this->actingAs($user)
        ->postJson(route('api.recipes.copy', [$user->currentTeam, $stored->json('data.id')]), ['team_id' => $team->id])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'chocolate-cake-1');
});

test('store returns the existing recipe when a client uuid is retried', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';

    $first = $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Soup', 'client_uuid' => $uuid])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Soup', 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Recipe::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store returns the existing recipe when a retry wins the race to insert it', function () {
    Storage::fake();
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $raced = createAfterLookup('recipes', 'client_uuid', fn () => Recipe::factory()->for($user->currentTeam)->create(['name' => 'Soup', 'client_uuid' => $uuid]));

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Soup', 'photo' => UploadedFile::fake()->image('soup.jpg'), 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $raced->model->id);

    expect(Recipe::where('client_uuid', $uuid)->count())->toBe(1)
        ->and(Storage::allFiles())->toBeEmpty();
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name']);
});

test('show returns a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonPath('data.id', $recipe->id)
        ->assertJsonPath('data.name', $recipe->name);
});

test('show returns photo_url when recipe has a photo', function () {
    Storage::fake();

    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'photo_path' => "teams/{$user->current_team_id}/recipes/cake.png",
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonMissingPath('data.photo_path');

    expect($response->json('data.photo_url'))->toBeString()->toContain('cake.png');
});

test('show returns null photo_url when recipe has no photo', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create(['photo_path' => null]);

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$user->currentTeam, $recipe]))
        ->assertOk()
        ->assertJsonPath('data.photo_url', null);
});

test('show returns 403 for a team the user does not belong to recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$recipe->team, $recipe]))
        ->assertForbidden();
});

test('update modifies a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patchJson(route('api.recipes.update', [$user->currentTeam, $recipe]), [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');

    $this->assertDatabaseHas('recipes', [
        'id' => $recipe->id,
        'name' => 'Updated Name',
    ]);
});

test('update returns 403 for a team the user does not belong to recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.recipes.update', [$recipe->team, $recipe]), ['name' => 'Nope'])
        ->assertForbidden();
});

test('destroy deletes a recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.recipes.destroy', [$user->currentTeam, $recipe]))
        ->assertNoContent();

    $this->assertSoftDeleted('recipes', ['id' => $recipe->id]);
});

test('destroy returns 403 for a team the user does not belong to recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.recipes.destroy', [$recipe->team, $recipe]))
        ->assertForbidden();
});

test('index returns recipes for another team the user belongs to without switching teams', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    Recipe::factory()->for($team)->count(2)->create();
    Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.index', $team))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($user->fresh()->current_team_id)->not->toBe($team->id);
});

test('store creates the recipe in the team from the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $team), ['name' => 'Pancakes'])
        ->assertCreated();

    expect($team->recipes()->count())->toBe(1)
        ->and($user->currentTeam->recipes()->count())->toBe(0);
});

test('show returns 404 when the recipe belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $recipe = Recipe::factory()->for($team)->create();

    $this->actingAs($user)
        ->getJson(route('api.recipes.show', [$user->currentTeam, $recipe]))
        ->assertNotFound();
});

test('store rejects a parent from another team', function () {
    $user = User::factory()->create();
    $parent = Recipe::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Pancakes', 'parent_id' => $parent->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});

test('store gives a recipe created from a parent a copy of the parent photo', function () {
    Storage::fake();
    $user = User::factory()->create();
    $parent = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Pancakes',
        'photo_path' => "teams/{$user->current_team_id}/recipes/pancakes.jpg",
        'thumb_path' => "teams/{$user->current_team_id}/recipes/thumbs/pancakes-abcd1234.jpg",
    ]);
    Storage::put($parent->photo_path, 'pancake photo');
    Storage::put($parent->thumb_path, 'pancake thumb');

    $response = $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Pancakes (Remix)', 'parent_id' => $parent->id])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id);

    $remix = Recipe::findOrFail($response->json('data.id'));

    expect($response->json('data.photo_url'))->toContain('/recipes/pancakes-remix-')
        ->and($response->json('data.thumb_url'))->toContain('/recipes/thumbs/pancakes-remix-')
        ->and($remix->photo_path)->not->toBe($parent->photo_path)
        ->and(Storage::get($remix->photo_path))->toBe('pancake photo')
        ->and(Storage::get($remix->thumb_path))->toBe('pancake thumb');
});

test('store does not copy the parent photo again when a client uuid is retried', function () {
    Storage::fake();
    $user = User::factory()->create();
    $parent = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Pancakes', 'photo_path' => "teams/{$user->current_team_id}/recipes/pancakes.jpg"]);
    Storage::put($parent->photo_path, 'pancake photo');
    $data = ['name' => 'Pancakes (Remix)', 'parent_id' => $parent->id, 'client_uuid' => '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d'];

    $first = $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), $data)
        ->assertCreated();
    $photoPath = Recipe::findOrFail($first->json('data.id'))->photo_path;

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), $data)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Recipe::findOrFail($first->json('data.id'))->photo_path)->toBe($photoPath)
        ->and(Storage::allFiles())->toHaveCount(2);
});

test('store leaves a recipe created from a parent without a photo when remove_photo is true', function () {
    Storage::fake();
    $user = User::factory()->create();
    $parent = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Pancakes', 'photo_path' => "teams/{$user->current_team_id}/recipes/pancakes.jpg"]);
    Storage::put($parent->photo_path, 'pancake photo');

    $response = $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Pancakes (Remix)', 'parent_id' => $parent->id, 'remove_photo' => true])
        ->assertCreated()
        ->assertJsonPath('data.parent_id', $parent->id)
        ->assertJsonPath('data.photo_url', null)
        ->assertJsonPath('data.thumb_url', null)
        ->assertJsonMissingPath('data.remove_photo');

    expect(Recipe::findOrFail($response->json('data.id'))->photo_path)->toBeNull()
        ->and(Storage::allFiles())->toBe([$parent->photo_path]);
});

test('store keeps the photo uploaded with a recipe created from a parent', function () {
    Storage::fake();
    $user = User::factory()->create();
    $parent = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Pancakes', 'photo_path' => "teams/{$user->current_team_id}/recipes/pancakes.jpg"]);
    Storage::put($parent->photo_path, 'pancake photo');
    $photo = UploadedFile::fake()->image('mine.png');

    $response = $this->actingAs($user)->post(route('api.recipes.store', $user->currentTeam), [
        'name' => 'Pancakes (Remix)', 'parent_id' => $parent->id, 'remove_photo' => '0', 'photo' => $photo,
    ], ['Accept' => 'application/json'])->assertCreated();

    $remix = Recipe::findOrFail($response->json('data.id'));

    expect($remix->photo_path)->toBe("teams/{$user->current_team_id}/recipes/pancakes-remix.png")
        ->and(Storage::get($remix->photo_path))->toBe($photo->getContent())
        ->and(Storage::allFiles())->toHaveCount(3);
});

test('store returns 422 when remove_photo is not true or false', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Pancakes', 'remove_photo' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['remove_photo' => 'The remove photo field must be true or false.']);

    expect(Recipe::count())->toBe(0);
});

test('recipe API stores tags and a photo and supports replacing and removing them', function () {
    Storage::fake();
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post(route('api.recipes.store', $user->currentTeam), [
        'name' => 'Photo cake', 'tags' => ['Dessert', 'Family favorite'],
        'photo' => UploadedFile::fake()->image('cake.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.tags', ['Dessert', 'Family favorite']);
    $recipe = Recipe::findOrFail($response->json('data.id'));
    $original = $recipe->photo_path;
    $originalThumb = $recipe->thumb_path;
    Storage::assertExists($original);
    Storage::assertExists($originalThumb);
    expect($response->json('data.thumb_url'))->toContain('/thumbs/');

    $this->post(route('api.recipes.update', [$user->currentTeam, $recipe]), [
        '_method' => 'PATCH', 'tags' => ['Vegan'], 'remove_photo' => '0',
        'photo' => UploadedFile::fake()->image('replacement.png'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.tags', ['Vegan']);
    $replacement = $recipe->fresh()->photo_path;
    $replacementThumb = $recipe->fresh()->thumb_path;
    Storage::assertMissing($original);
    Storage::assertMissing($originalThumb);
    Storage::assertExists($replacement);
    Storage::assertExists($replacementThumb);

    $this->patchJson(route('api.recipes.update', [$user->currentTeam, $recipe]), ['name' => 'Renamed cake'])
        ->assertOk()->assertJsonPath('data.tags', ['Vegan']);
    expect($recipe->fresh()->photo_path)->toBe($replacement);

    $this->patchJson(route('api.recipes.update', [$user->currentTeam, $recipe]), ['tags' => [], 'remove_photo' => true])
        ->assertOk()->assertJsonPath('data.tags', [])->assertJsonPath('data.photo_url', null)->assertJsonPath('data.thumb_url', null);
    Storage::assertMissing($replacement);
    Storage::assertMissing($replacementThumb);
});

test('recipe API rejects invalid tags and photos on create and update', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();
    $data = ['name' => 'Bad photo', 'tags' => [123], 'photo' => UploadedFile::fake()->create('not-image.txt')];
    $this->actingAs($user)->postJson(route('api.recipes.store', $user->currentTeam), $data)
        ->assertUnprocessable()->assertJsonValidationErrors(['tags.0', 'photo']);
    $this->patchJson(route('api.recipes.update', [$user->currentTeam, $recipe]), $data)
        ->assertUnprocessable()->assertJsonValidationErrors(['tags.0', 'photo']);
});

test('recipe API stores ingredients and instructions as rows from text or a list', function () {
    $user = User::factory()->create();

    $id = $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), [
            'name' => 'Apple Cider Donuts',
            'ingredients' => "# Wet\n1/2 cup apple cider",
            'instructions' => 'Bake for 8 minutes.',
        ])
        ->assertCreated()
        ->assertJsonPath('data.ingredients', [
            ['type' => 'heading', 'text' => 'Wet'],
            ['type' => 'item', 'text' => '1/2 cup apple cider', 'quantity' => 0.5, 'quantity_max' => null, 'unit' => 'cup', 'name' => 'apple cider'],
        ])
        ->assertJsonPath('data.instructions', [
            ['type' => 'step', 'text' => 'Bake for 8 minutes.', 'timers' => [['seconds' => 480]]],
        ])
        ->json('data.id');

    $this->actingAs($user)
        ->patchJson(route('api.recipes.update', [$user->currentTeam, $id]), [
            'ingredients' => [['type' => 'item', 'text' => '2 cups flour'], '1 tsp salt'],
            'instructions' => null,
        ])
        ->assertOk()
        ->assertJsonPath('data.ingredients.0.name', 'flour')
        ->assertJsonPath('data.ingredients.1.unit', 'tsp')
        ->assertJsonPath('data.instructions', null);
});

test('recipe API rejects ingredients and instructions that are not text or a list of entries', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), [
            'name' => 'Apple Cider Donuts',
            'ingredients' => 5,
            'instructions' => [['quantity' => 2]],
        ])
        ->assertJsonValidationErrors(['ingredients', 'instructions']);
});

test('copy copies a recipe and its photo to another team of the user', function () {
    Storage::fake();
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $recipe = Recipe::factory()->for($user->currentTeam)->create([
        'name' => 'Chocolate Cake',
        'photo_path' => "teams/{$user->current_team_id}/recipes/cake.png",
        'share_token' => 'abc',
    ]);
    Storage::put($recipe->photo_path, 'cake photo');

    $this->actingAs($user)
        ->postJson(route('api.recipes.copy', [$user->currentTeam, $recipe]), ['team_id' => $team->id])
        ->assertCreated()
        ->assertJsonPath('data.team_id', $team->id)
        ->assertJsonPath('data.parent_id', $recipe->id)
        ->assertJsonPath('data.name', 'Chocolate Cake')
        ->assertJsonPath('data.share_token', null);

    $copy = $team->recipes()->sole();

    expect($copy->photo_path)->toStartWith("teams/{$team->id}/recipes/chocolate-cake-")
        ->and(Storage::get($copy->photo_path))->toBe('cake photo')
        ->and($recipe->fresh()->team_id)->toBe($user->current_team_id);
});

test('copy returns the existing copy when a client uuid is retried', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $recipe = Recipe::factory()->for($user->currentTeam)->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';

    $first = $this->actingAs($user)
        ->postJson(route('api.recipes.copy', [$user->currentTeam, $recipe]), ['team_id' => $team->id, 'client_uuid' => $uuid])
        ->assertCreated()
        ->assertJsonPath('data.client_uuid', $uuid);

    $this->actingAs($user)
        ->postJson(route('api.recipes.copy', [$user->currentTeam, $recipe]), ['team_id' => $team->id, 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect($team->recipes()->count())->toBe(1);
});

test('copy returns the existing copy when a retry wins the race to insert it', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $recipe = Recipe::factory()->for($user->currentTeam)->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $raced = createAfterLookup('recipes', 'client_uuid', fn () => Recipe::factory()->for($team)->create(['name' => $recipe->name, 'parent_id' => $recipe->id, 'client_uuid' => $uuid]));

    $this->actingAs($user)
        ->postJson(route('api.recipes.copy', [$user->currentTeam, $recipe]), ['team_id' => $team->id, 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $raced->model->id);

    expect($team->recipes()->count())->toBe(1);
});

test('copy validates the team', function (Closure $teamId) {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.copy', [$user->currentTeam, $recipe]), ['team_id' => $teamId($user), 'client_uuid' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['team_id', 'client_uuid']);

    expect(Recipe::count())->toBe(1);
})->with([
    'missing' => fn () => fn (User $user) => null,
    'the same team' => fn () => fn (User $user) => $user->current_team_id,
    'a team the user does not belong to' => fn () => fn (User $user) => Team::factory()->create()->id,
]);

test('copy returns 403 for a team the user does not belong to recipe', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.copy', [$recipe->team, $recipe]), ['team_id' => $user->current_team_id])
        ->assertForbidden();

    expect($user->currentTeam->recipes()->count())->toBe(0);
});
