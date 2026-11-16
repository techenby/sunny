<?php

use App\Support\Amount;

it('formats a scaled ingredient amount', function (array $row, float $factor, ?string $amount, string $text) {
    expect(Amount::scale($row, $factor))->toBe(['amount' => $amount, 'text' => $text]);
})->with([
    'unchanged' => [['text' => '1/4 cup granulated sugar', 'quantity' => 0.25, 'unit' => 'cup', 'name' => 'granulated sugar'], 1.0, '1/4 cup', 'granulated sugar'],
    'doubled past one becomes plural' => [['text' => '1 cup flour', 'quantity' => 1, 'unit' => 'cup', 'name' => 'flour'], 2.0, '2 cups', 'flour'],
    'mixed number' => [['text' => '1 1/2 cups flour', 'quantity' => 1.5, 'unit' => 'cup', 'name' => 'flour'], 1.0, '1 1/2 cups', 'flour'],
    'range' => [['text' => '2-3 tbsp olive oil', 'quantity' => 2, 'quantity_max' => 3, 'unit' => 'tbsp', 'name' => 'olive oil'], 2.0, '4–6 Tbsp', 'olive oil'],
    'count without a unit' => [['text' => '3 eggs', 'quantity' => 3, 'unit' => null, 'name' => 'eggs'], 0.5, '1 1/2', 'eggs'],
    'irregular plural' => [['text' => '1 pinch salt', 'quantity' => 1, 'unit' => 'pinch', 'name' => 'salt'], 2.0, '2 pinches', 'salt'],
    'abbreviations are not pluralised' => [['text' => '8 oz cream cheese', 'quantity' => 8, 'unit' => 'oz', 'name' => 'cream cheese'], 2.0, '16 oz', 'cream cheese'],
    'metric keeps a decimal' => [['text' => '15 g yeast', 'quantity' => 15, 'unit' => 'g', 'name' => 'yeast'], 1.5, '22.5 g', 'yeast'],
    'awkward fraction falls back to a decimal' => [['text' => '1 cup stock', 'quantity' => 1, 'unit' => 'cup', 'name' => 'stock'], 0.2, '0.2 cup', 'stock'],
    'prefers the words after the amount when the server sends them' => [['text' => '1 tsp salt, to taste', 'quantity' => 1, 'unit' => 'tsp', 'name' => 'salt', 'rest' => 'salt, to taste'], 2.0, '2 tsp', 'salt, to taste'],
    'no amount' => [['text' => 'Salt and black pepper', 'quantity' => null, 'unit' => null, 'name' => 'Salt and black pepper'], 2.0, null, 'Salt and black pepper'],
    'a row that has not synced yet' => [['type' => 'item', 'text' => '2 cups flour'], 2.0, null, '2 cups flour'],
]);
