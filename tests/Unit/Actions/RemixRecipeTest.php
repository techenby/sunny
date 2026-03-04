<?php

use App\Actions\RemixRecipe;
use App\Models\Recipe;

test('it creates a remix of the recipe', function () {
    $recipe = Recipe::factory()->create([
        'name' => 'Chocolate Cake',
        'source' => 'https://example.com',
        'servings' => '8',
        'ingredients' => '<ul><li>flour</li></ul>',
        'instructions' => '<ol><li>mix</li></ol>',
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
