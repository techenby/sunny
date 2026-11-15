<?php

use App\Models\Recipe;
use Illuminate\Support\Facades\DB;

test('recipe html is converted to rows and back', function () {
    $migration = require database_path('migrations/2026_11_15_200000_store_recipe_ingredients_and_instructions_as_rows.php');
    $recipe = Recipe::factory()->create();
    $empty = Recipe::factory()->create();

    $migration->down();

    DB::table('recipes')->where('id', $recipe->id)->update([
        'ingredients' => '<h3>Wet</h3><ul><li><p>1/2 cup apple cider</p></li></ul><h3>Dry</h3><ul><li><p>Salt</p></li></ul>',
        'instructions' => '<ol><li><p>Mix.</p></li><li><p>Bake for 8–9 minutes.</p></li></ol>',
    ]);

    $migration->up();

    expect($recipe->fresh())
        ->ingredients->toBe([
            ['type' => 'heading', 'text' => 'Wet'],
            ['type' => 'item', 'text' => '1/2 cup apple cider', 'quantity' => 0.5, 'quantity_max' => null, 'unit' => 'cup', 'name' => 'apple cider'],
            ['type' => 'heading', 'text' => 'Dry'],
            ['type' => 'item', 'text' => 'Salt', 'quantity' => null, 'quantity_max' => null, 'unit' => null, 'name' => 'Salt'],
        ])
        ->instructions->toBe([
            ['type' => 'step', 'text' => 'Mix.', 'timers' => []],
            ['type' => 'step', 'text' => 'Bake for 8–9 minutes.', 'timers' => [['seconds' => 480]]],
        ])
        ->and($empty->fresh())
        ->ingredients->toBeNull()
        ->instructions->toBeNull();

    $migration->down();

    expect(DB::table('recipes')->find($recipe->id))
        ->ingredients->toBe('<ul><li><strong>Wet</strong></li><li>1/2 cup apple cider</li><li><strong>Dry</strong></li><li>Salt</li></ul>')
        ->instructions->toBe('<ol><li>Mix.</li><li>Bake for 8–9 minutes.</li></ol>');

    $migration->up();

    expect(array_column($recipe->fresh()->ingredients, 'type'))->toBe(['heading', 'item', 'heading', 'item']);
});
