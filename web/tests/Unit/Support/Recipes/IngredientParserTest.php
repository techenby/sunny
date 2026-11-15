<?php

use App\Support\Recipes\IngredientParser;

test('it parses the amount, unit and name from an ingredient line', function (string $text, ?float $quantity, ?float $max, ?string $unit, string $name) {
    expect(IngredientParser::parse($text))->toBe([
        'quantity' => $quantity,
        'quantity_max' => $max,
        'unit' => $unit,
        'name' => $name,
    ]);
})->with([
    'whole number' => ['2 cups flour', 2.0, null, 'cup', 'flour'],
    'fraction' => ['1/4 cup granulated sugar', 0.25, null, 'cup', 'granulated sugar'],
    'mixed number' => ['1 1/2 cups all purpose flour', 1.5, null, 'cup', 'all purpose flour'],
    'hyphenated mixed number' => ['1-1/2 cups milk', 1.5, null, 'cup', 'milk'],
    'unicode fraction' => ['½ cup cream', 0.5, null, 'cup', 'cream'],
    'number with unicode fraction' => ['2¼ cups flour', 2.25, null, 'cup', 'flour'],
    'decimal' => ['1.5 lbs ground beef', 1.5, null, 'lb', 'ground beef'],
    'range' => ['2-3 tbsp olive oil', 2.0, 3.0, 'tbsp', 'olive oil'],
    'worded range' => ['1 to 2 teaspoons salt', 1.0, 2.0, 'tsp', 'salt'],
    'capital T is a tablespoon' => ['2 T butter', 2.0, null, 'tbsp', 'butter'],
    'lowercase t is a teaspoon' => ['2 t vanilla', 2.0, null, 'tsp', 'vanilla'],
    'abbreviation with a period' => ['2 Tbsp. brown sugar, packed', 2.0, null, 'tbsp', 'brown sugar, packed'],
    'unit attached to the number' => ['200g flour', 200.0, null, 'g', 'flour'],
    'count without a unit' => ['3 eggs', 3.0, null, null, 'eggs'],
    'descriptor is not a unit' => ['2 large eggs', 2.0, null, null, 'large eggs'],
    'count unit' => ['3 cloves garlic', 3.0, null, 'clove', 'garlic'],
    'unit followed by of' => ['1 pinch of salt', 1.0, null, 'pinch', 'salt'],
    'package size in parentheses' => ['2 (28 oz) cans whole tomatoes', 2.0, null, null, '(28 oz) cans whole tomatoes'],
    'to taste' => ['Salt & pepper to taste', null, null, null, 'Salt & pepper'],
    'to taste in parentheses' => ['salt & pepper (to taste)', null, null, null, 'salt & pepper'],
    'to taste after a comma' => ['Parmesan, to taste', null, null, null, 'Parmesan'],
    'to taste after an amount' => ['1 tsp salt, to taste', 1.0, null, 'tsp', 'salt'],
    'a pinch' => ['A pinch of cayenne', null, null, 'pinch', 'cayenne'],
    'pinch without an article' => ['Pinch of ground nutmeg', null, null, 'pinch', 'ground nutmeg'],
    'handful' => ['Handful of basil', null, null, 'handful', 'basil'],
    'counted handfuls' => ['2 handfuls spinach', 2.0, null, 'handful', 'spinach'],
    'no amount' => ['Salt and black pepper', null, null, null, 'Salt and black pepper'],
    'amount later in the line' => ['Zest of 1 lemon', null, null, null, 'Zest of 1 lemon'],
    'dimensions are not an amount' => ['9x13-inch baking dish', null, null, null, '9x13-inch baking dish'],
    'a size is not an amount' => ['1-inch piece ginger', null, null, null, '1-inch piece ginger'],
    'a percentage is not an amount' => ['2% milk', null, null, null, '2% milk'],
]);
