<?php

use App\Actions\CreateRecipe;
use App\Models\Recipe;
use App\Models\Team;

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
        'ingredients' => '<ul><li>2 cups flour</li></ul>',
        'instructions' => '<ol><li>Mix ingredients.</li></ol>',
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
        ->ingredients->toBe('<ul><li>2 cups flour</li></ul>')
        ->instructions->toBe('<ol><li>Mix ingredients.</li></ol>')
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
