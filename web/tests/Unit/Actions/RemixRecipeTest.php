<?php

use App\Actions\Recipes\RemixRecipe;
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

test('it does not copy the client uuid', function () {
    $recipe = Recipe::factory()->create(['client_uuid' => '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d']);

    $remix = (new RemixRecipe)->handle($recipe);

    expect($remix->client_uuid)->toBeNull();
});
