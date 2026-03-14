<?php

use App\Actions\UpdateRecipe;
use App\Models\Recipe;

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
