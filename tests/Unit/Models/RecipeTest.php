<?php

use App\Models\Recipe;

test('can get shortened source when link', function () {
    $recipe = Recipe::factory()->create(['source' => 'https://www.wonderfulrecipes.com/gibberish-foo-bar']);

    expect($recipe->shortenedSource)->toBe('wonderfulrecipes.com');
});

test('can get shortened source with no subdomain', function () {
    $recipe = Recipe::factory()->create(['source' => 'https://wonderfulrecipes.com/gibberish-foo-bar']);

    expect($recipe->shortenedSource)->toBe('wonderfulrecipes.com');
});

test('can get shortened source with query string', function () {
    $recipe = Recipe::factory()->create(['source' => 'https://wonderfulrecipes.com?search=oden']);

    expect($recipe->shortenedSource)->toBe('wonderfulrecipes.com');
});
