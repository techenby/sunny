<?php

use App\Support\Recipes\Amount;
use App\Support\Recipes\RecipeLines;

test('it formats a scaled ingredient amount', function (string $text, float $factor, ?string $amount, string $rest) {
    $row = RecipeLines::ingredients($text)[0];

    expect(Amount::scale($row, $factor))->toBe(['amount' => $amount, 'text' => $rest]);
})->with([
    'unchanged' => ['1/4 cup granulated sugar', 1.0, '1/4 cup', 'granulated sugar'],
    'doubled to a whole' => ['1/2 cup apple cider', 2.0, '1 cup', 'apple cider'],
    'doubled past one becomes plural' => ['1 cup flour', 2.0, '2 cups', 'flour'],
    'mixed number' => ['1 1/2 cups all purpose flour', 1.0, '1 1/2 cups', 'all purpose flour'],
    'halved into eighths' => ['1/4 tsp cinnamon', 0.5, '1/8 tsp', 'cinnamon'],
    'thirds' => ['1 cup milk', 1 / 3, '1/3 cup', 'milk'],
    'scaled by servings' => ['2 Tbsp brown sugar, packed', 18 / 12, '3 Tbsp', 'brown sugar, packed'],
    'unicode fraction' => ['½ tsp salt', 3.0, '1 1/2 tsp', 'salt'],
    'range' => ['2-3 tbsp olive oil', 2.0, '4–6 Tbsp', 'olive oil'],
    'count without a unit' => ['3 eggs', 0.5, '1 1/2', 'eggs'],
    'count unit becomes plural' => ['1 clove garlic', 3.0, '3 cloves', 'garlic'],
    'metric stays decimal' => ['250 g flour', 1.5, '375 g', 'flour'],
    'small metric keeps a decimal' => ['5 ml vanilla', 0.5, '2.5 ml', 'vanilla'],
    'awkward fraction falls back to a decimal' => ['1 cup stock', 0.2, '0.2 cup', 'stock'],
    'keeps the words after the amount' => ['1 tsp salt, to taste', 2.0, '2 tsp', 'salt, to taste'],
    'no amount' => ['Salt and black pepper', 2.0, null, 'Salt and black pepper'],
    'pinch without a number' => ['Pinch of ground nutmeg', 2.0, null, 'Pinch of ground nutmeg'],
]);
