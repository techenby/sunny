<?php

use App\Actions\Recipes\RemixRecipe;
use App\Actions\Recipes\UpdateRecipe;
use App\Models\Recipe;
use Illuminate\Support\Facades\Storage;

test('it creates a remix of the recipe', function () {
    $recipe = Recipe::factory()->create([
        'name' => 'Chocolate Cake',
        'source' => 'https://example.com',
        'servings' => '8',
        'ingredients' => 'flour',
        'instructions' => 'mix',
    ]);

    $remix = (new RemixRecipe)->handle($recipe);

    expect($remix)
        ->toBeInstanceOf(Recipe::class)
        ->id->not->toBe($recipe->id)
        ->name->toBe('Chocolate Cake (Remix)')
        ->parent_id->toBe($recipe->id)
        ->team_id->toBe($recipe->team_id)
        ->source->toBe($recipe->source)
        ->servings->toBe($recipe->servings)
        ->ingredients->toBe($recipe->ingredients)
        ->instructions->toBe($recipe->instructions);
});

test('it sets the parent relationship', function () {
    $recipe = Recipe::factory()->create();

    $remix = (new RemixRecipe)->handle($recipe);

    expect($remix->parent->id)->toBe($recipe->id);
    expect($recipe->fresh()->remixes)->toHaveCount(1);
});

test('it does not copy the client uuid', function () {
    $recipe = Recipe::factory()->create(['client_uuid' => '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d']);

    $remix = (new RemixRecipe)->handle($recipe);

    expect($remix->client_uuid)->toBeNull();
});

test('it remixes a shared recipe without copying its share link', function () {
    $recipe = Recipe::factory()->shared()->create();

    $remix = (new RemixRecipe)->handle($recipe);

    expect($remix->exists)->toBeTrue()
        ->and($remix->share_token)->toBeNull()
        ->and($recipe->fresh()->isShared())->toBeTrue();
});

test('the remix keeps its photo when the original removes its own', function () {
    Storage::fake();
    Storage::put('teams/1/recipes/stew.jpg', 'stew photo');
    Storage::put('teams/1/recipes/thumbs/stew-abcd1234.jpg', 'stew thumb');
    $recipe = Recipe::factory()->create(['name' => 'Stew', 'photo_path' => 'teams/1/recipes/stew.jpg', 'thumb_path' => 'teams/1/recipes/thumbs/stew-abcd1234.jpg']);
    $remix = (new RemixRecipe)->handle($recipe);

    (new UpdateRecipe)->handle($recipe, [], removePhoto: true);

    expect($remix->fresh()->photo_path)->not->toBe('teams/1/recipes/stew.jpg')
        ->and(Storage::get($remix->fresh()->photo_path))->toBe('stew photo')
        ->and(Storage::get($remix->fresh()->thumb_path))->toBe('stew thumb');
    Storage::assertMissing(['teams/1/recipes/stew.jpg', 'teams/1/recipes/thumbs/stew-abcd1234.jpg']);
});

test('a second remix gets its own slug, even after the first is deleted', function () {
    $recipe = Recipe::factory()->create(['name' => 'Stew']);
    (new RemixRecipe)->handle($recipe)->delete();

    $remix = (new RemixRecipe)->handle($recipe);

    expect($remix)->name->toBe('Stew (Remix)')
        ->slug->toBe('stew-remix-1');
});
