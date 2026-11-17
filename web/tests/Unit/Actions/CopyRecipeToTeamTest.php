<?php

use App\Actions\Recipes\CopyRecipeToTeam;
use App\Actions\Recipes\UpdateRecipe;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('it gives the copy its own photo files', function () {
    Storage::fake();
    Storage::put('teams/1/recipes/stew.jpg', 'stew photo');
    Storage::put('teams/1/recipes/thumbs/stew-abcd1234.jpg', 'stew thumb');
    $recipe = Recipe::factory()->create(['name' => 'Stew', 'photo_path' => 'teams/1/recipes/stew.jpg', 'thumb_path' => 'teams/1/recipes/thumbs/stew-abcd1234.jpg']);
    $cabin = Team::factory()->create();

    $copy = (new CopyRecipeToTeam)->handle($recipe, $cabin);

    expect($copy->photo_path)->toStartWith("teams/{$cabin->id}/recipes/stew-")->toEndWith('.jpg')
        ->and($copy->thumb_path)->toStartWith("teams/{$cabin->id}/recipes/thumbs/stew-")
        ->and(Storage::get($copy->photo_path))->toBe('stew photo')
        ->and(Storage::get($copy->thumb_path))->toBe('stew thumb');
});

test('the copy keeps its photo when the original replaces its own', function () {
    Storage::fake();
    Storage::put('teams/1/recipes/stew.jpg', 'stew photo');
    $recipe = Recipe::factory()->create(['name' => 'Stew', 'photo_path' => 'teams/1/recipes/stew.jpg']);
    $copy = (new CopyRecipeToTeam)->handle($recipe, Team::factory()->create());

    (new UpdateRecipe)->handle($recipe, ['photo' => UploadedFile::fake()->image('new-stew.jpg')]);

    expect(Storage::get($copy->fresh()->photo_path))->toBe('stew photo');
});

test('it copies a recipe without a photo', function () {
    Storage::fake();
    $recipe = Recipe::factory()->create();

    $copy = (new CopyRecipeToTeam)->handle($recipe, Team::factory()->create());

    expect($copy->photo_path)->toBeNull()
        ->and($copy->thumb_path)->toBeNull();
});
