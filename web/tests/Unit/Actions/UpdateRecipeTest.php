<?php

use App\Actions\Recipes\UpdateRecipe;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('it updates the recipe with the given data', function () {
    $recipe = Recipe::factory()->create([
        'name' => 'Old Name',
        'servings' => '4',
    ]);

    $updated = (new UpdateRecipe)->handle($recipe, [
        'name' => 'New Name',
        'servings' => '8',
        'description' => 'Updated description.',
    ]);

    expect($updated->fresh())
        ->name->toBe('New Name')
        ->servings->toBe('8')
        ->description->toBe('Updated description.');
});

test('it returns the updated recipe', function () {
    $recipe = Recipe::factory()->create();

    $result = (new UpdateRecipe)->handle($recipe, ['name' => 'Updated']);

    expect($result)->toBeInstanceOf(Recipe::class)
        ->id->toBe($recipe->id);
});

test('it keeps a photo file another recipe still uses', function () {
    Storage::fake();
    $team = Team::factory()->create();
    $shared = "teams/{$team->id}/recipes/stew.jpg";
    Storage::put($shared, 'stew photo');
    $recipe = Recipe::factory()->for($team)->create(['name' => 'Stew', 'photo_path' => $shared]);
    $copy = Recipe::factory()->create(['photo_path' => $shared]);
    $copy->delete();

    (new UpdateRecipe)->handle($recipe, ['photo' => UploadedFile::fake()->image('new-stew.jpg')]);

    expect($recipe->photo_path)->not->toBe($shared)->toStartWith("teams/{$team->id}/recipes/stew-")
        ->and(Storage::get($shared))->toBe('stew photo');
    Storage::assertExists($recipe->photo_path);
});

test('it deletes the old photo files once no recipe uses them', function () {
    Storage::fake();
    Storage::put('teams/1/recipes/stew.jpg', 'stew photo');
    Storage::put('teams/1/recipes/thumbs/stew-abcd1234.jpg', 'stew thumb');
    $recipe = Recipe::factory()->create(['photo_path' => 'teams/1/recipes/stew.jpg', 'thumb_path' => 'teams/1/recipes/thumbs/stew-abcd1234.jpg']);

    (new UpdateRecipe)->handle($recipe, [], removePhoto: true);

    expect($recipe->photo_path)->toBeNull();
    Storage::assertMissing(['teams/1/recipes/stew.jpg', 'teams/1/recipes/thumbs/stew-abcd1234.jpg']);
});
