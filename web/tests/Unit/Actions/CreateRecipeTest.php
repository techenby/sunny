<?php

use App\Actions\Recipes\CreateRecipe;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('it creates a recipe for the given team', function () {
    $team = Team::factory()->create();

    $recipe = (new CreateRecipe)->handle($team, [
        'name' => 'Chocolate Cake',
        'source' => 'https://example.com/chocolate-cake',
        'servings' => '8',
        'prep_time' => '15m',
        'cook_time' => '45m',
        'total_time' => '1h',
        'description' => 'A rich chocolate cake.',
        'ingredients' => '2 cups flour',
        'instructions' => 'Mix ingredients.',
        'notes' => 'Best served warm.',
        'nutrition' => 'Calories: 350',
    ]);

    expect($recipe)
        ->toBeInstanceOf(Recipe::class)
        ->team_id->toBe($team->id)
        ->name->toBe('Chocolate Cake')
        ->source->toBe('https://example.com/chocolate-cake')
        ->servings->toBe('8')
        ->prep_time->toBe('15m')
        ->cook_time->toBe('45m')
        ->total_time->toBe('1h')
        ->description->toBe('A rich chocolate cake.')
        ->ingredients->toBe([['type' => 'item', 'text' => '2 cups flour', 'quantity' => 2.0, 'quantity_max' => null, 'unit' => 'cup', 'name' => 'flour']])
        ->instructions->toBe([['type' => 'step', 'text' => 'Mix ingredients.', 'timers' => []]])
        ->notes->toBe('Best served warm.')
        ->nutrition->toBe('Calories: 350');

    expect($team->recipes)->toHaveCount(1);
});

test('it creates a recipe with minimal data', function () {
    $team = Team::factory()->create();

    $recipe = (new CreateRecipe)->handle($team, [
        'name' => 'Simple Recipe',
    ]);

    expect($recipe)
        ->toBeInstanceOf(Recipe::class)
        ->name->toBe('Simple Recipe')
        ->source->toBeNull()
        ->servings->toBeNull();
});

test('a recipe created from a parent gets its own copy of the parent photo and thumbnail', function () {
    Storage::fake();
    $team = Team::factory()->create();
    $parent = Recipe::factory()->for($team)->create(['name' => 'Stew', 'photo_path' => "teams/{$team->id}/recipes/stew.jpg", 'thumb_path' => "teams/{$team->id}/recipes/thumbs/stew-abcd1234.jpg"]);
    Storage::put($parent->photo_path, 'stew photo');
    Storage::put($parent->thumb_path, 'stew thumb');

    $recipe = (new CreateRecipe)->handle($team, ['name' => 'Stew (Remix)', 'parent_id' => $parent->id]);

    expect($recipe->photo_path)->toStartWith("teams/{$team->id}/recipes/stew-remix-")->toEndWith('.jpg')
        ->and($recipe->thumb_path)->toStartWith("teams/{$team->id}/recipes/thumbs/stew-remix-")
        ->and(Storage::get($recipe->photo_path))->toBe('stew photo')
        ->and(Storage::get($recipe->thumb_path))->toBe('stew thumb');
    Storage::assertExists([$parent->photo_path, $parent->thumb_path]);
});

test('a recipe created from a parent keeps the photo it was created with', function () {
    Storage::fake();
    $team = Team::factory()->create();
    $parent = Recipe::factory()->for($team)->create(['name' => 'Stew', 'photo_path' => "teams/{$team->id}/recipes/stew.jpg"]);
    Storage::put($parent->photo_path, 'stew photo');

    $recipe = (new CreateRecipe)->handle($team, ['name' => 'Stew (Remix)', 'parent_id' => $parent->id, 'photo' => UploadedFile::fake()->image('mine.png')]);

    expect($recipe->photo_path)->toBe("teams/{$team->id}/recipes/stew-remix.png")
        ->and(Storage::allFiles())->toHaveCount(3);
});

test('a recipe created from a parent has no photo when it is created without the parent photo', function () {
    Storage::fake();
    $team = Team::factory()->create();
    $parent = Recipe::factory()->for($team)->create(['name' => 'Stew', 'photo_path' => "teams/{$team->id}/recipes/stew.jpg"]);
    Storage::put($parent->photo_path, 'stew photo');

    $recipe = (new CreateRecipe)->handle($team, ['name' => 'Stew (Remix)', 'parent_id' => $parent->id], withoutParentPhoto: true);

    expect($recipe)
        ->parent_id->toBe($parent->id)
        ->photo_path->toBeNull()
        ->thumb_path->toBeNull()
        ->and(Storage::allFiles())->toBe([$parent->photo_path]);
});

test('a recipe created from a parent has no photo when the parent has none to copy', function (?string $photoPath) {
    Storage::fake();
    $team = Team::factory()->create();
    $parent = Recipe::factory()->for($team)->create(['name' => 'Stew', 'photo_path' => $photoPath]);

    $recipe = (new CreateRecipe)->handle($team, ['name' => 'Stew (Remix)', 'parent_id' => $parent->id]);

    expect($recipe)
        ->parent_id->toBe($parent->id)
        ->photo_path->toBeNull()
        ->thumb_path->toBeNull();
})->with([
    'no photo' => null,
    'a photo whose file is gone' => 'teams/1/recipes/stew.jpg',
]);

test('a recipe created from a deleted parent still gets a copy of its photo', function () {
    Storage::fake();
    $team = Team::factory()->create();
    $parent = Recipe::factory()->for($team)->create(['name' => 'Stew', 'photo_path' => "teams/{$team->id}/recipes/stew.jpg"]);
    Storage::put($parent->photo_path, 'stew photo');
    $parent->delete();

    $recipe = (new CreateRecipe)->handle($team, ['name' => 'Stew (Remix)', 'parent_id' => $parent->id]);

    expect(Storage::get($recipe->photo_path))->toBe('stew photo');
});
