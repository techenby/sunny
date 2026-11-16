<?php

use App\Support\Recipes\RecipeLines;

test('it turns text into ingredient rows with headings', function () {
    expect(RecipeLines::ingredients("# Wet\n1/2 cup apple cider\n\n- 1 tsp vanilla\n#Dry\nSalt"))->toBe([
        ['type' => 'heading', 'text' => 'Wet'],
        ['type' => 'item', 'text' => '1/2 cup apple cider', 'quantity' => 0.5, 'quantity_max' => null, 'unit' => 'cup', 'name' => 'apple cider'],
        ['type' => 'item', 'text' => '1 tsp vanilla', 'quantity' => 1.0, 'quantity_max' => null, 'unit' => 'tsp', 'name' => 'vanilla'],
        ['type' => 'heading', 'text' => 'Dry'],
        ['type' => 'item', 'text' => 'Salt', 'quantity' => null, 'quantity_max' => null, 'unit' => null, 'name' => 'Salt'],
    ]);
});

test('it turns text into instruction rows with timers', function () {
    expect(RecipeLines::instructions("1. Mix everything.\n2) Bake for 8 minutes."))->toBe([
        ['type' => 'step', 'text' => 'Mix everything.', 'timers' => []],
        ['type' => 'step', 'text' => 'Bake for 8 minutes.', 'timers' => [['seconds' => 480]]],
    ]);
});

test('it keeps a leading number on an ingredient', function () {
    expect(RecipeLines::ingredients('2. eggs')[0]['text'])->toBe('2. eggs');
});

test('it accepts lists of strings and rows', function () {
    $rows = RecipeLines::ingredients([
        '# Sauce',
        ['type' => 'heading', 'text' => 'Topping'],
        ['text' => '1 cup sugar', 'quantity' => 99],
        '  ',
    ]);

    expect(array_column($rows, 'type'))->toBe(['heading', 'heading', 'item'])
        ->and($rows[2]['quantity'])->toBe(1.0);
});

test('it returns null when there is nothing to store', function (mixed $value) {
    expect(RecipeLines::ingredients($value))->toBeNull()
        ->and(RecipeLines::instructions($value))->toBeNull();
})->with([null, '', " \n ", [[]], 5]);

test('it turns rows back into editable text', function () {
    $text = "# Wet\n1/2 cup apple cider\n# Dry\nSalt";

    expect(RecipeLines::text(RecipeLines::ingredients($text)))->toBe($text)
        ->and(RecipeLines::text(null))->toBe('');
});

test('it groups rows under their headings and keeps step numbers running', function () {
    $groups = RecipeLines::groups(RecipeLines::instructions("Preheat.\n# Batter\nMix.\nPour.\n# Finish\nBake."));

    expect(array_column($groups, 'heading'))->toBe([null, 'Batter', 'Finish'])
        ->and(array_column($groups, 'start'))->toBe([1, 2, 4])
        ->and(array_map(fn (array $group): int => count($group['rows']), $groups))->toBe([1, 2, 1])
        ->and(RecipeLines::groups(null))->toBe([]);
});

test('it converts editor html into lines', function (string $html, array $lines) {
    expect(RecipeLines::fromHtml($html))->toBe($lines);
})->with([
    'a list' => ['<ul><li>2 cups flour</li><li>1 cup sugar</li></ul>', ['2 cups flour', '1 cup sugar']],
    'paragraphs inside list items' => ['<ul><li><p>2 cups flour</p></li><li><p>½ cup cream &amp; butter</p></li></ul>', ['2 cups flour', '½ cup cream & butter']],
    'headings between lists' => ['<h3>Wet Ingredients</h3><ul><li>1 cup cider</li></ul><h3>Dry</h3><ul><li>Salt</li></ul>', ['# Wet Ingredients', '1 cup cider', '# Dry', 'Salt']],
    'bold paragraphs are headings' => ['<p><strong>Topping</strong></p><ul><li>3 Tbsp sugar</li></ul>', ['# Topping', '3 Tbsp sugar']],
    'partly bold paragraphs are lines' => ['<p><strong>Note:</strong> chill first</p>', ['Note: chill first']],
    'nested lists become sections' => ['<ol><li><strong>Make the sauce</strong><ol><li>Simmer.</li><li>Blend.</li></ol></li><li>Serve.</li></ol>', ['# Make the sauce', 'Simmer.', 'Blend.', 'Serve.']],
    'fully bold list items are headings' => ['<ul><li><strong>Wet</strong></li><li>1 cup cider</li></ul>', ['# Wet', '1 cup cider']],
    'inline formatting is dropped' => ['<ol><li>Bake at <strong>375°F</strong> for <a href="#">8 minutes</a>.</li></ol>', ['Bake at 375°F for 8 minutes.']],
    'lines separated by breaks' => ['<div>4 lbs potatoes<br>1 c. sour cream<br><br>½ tsp. paprika</div>', ['4 lbs potatoes', '1 c. sour cream', '½ tsp. paprika']],
    'bold lines between breaks are headings' => ['<div>1 tbs yeast<br><br><strong>Glaze<br></strong>½ tsp vanilla</div>', ['1 tbs yeast', '# Glaze', '½ tsp vanilla']],
    'inline formatting stays on one line between breaks' => ['<div>Fold in banana, <strong><em>or</em></strong> grated carrots<br>Bake.</div>', ['Fold in banana, or grated carrots', 'Bake.']],
    'a bold block before a list is a heading' => ['<div><strong>Batter</strong></div><ol><li>Preheat oven to 400</li></ol><div><br><strong>Topping</strong></div><ol><li>Mix.</li></ol>', ['# Batter', 'Preheat oven to 400', '# Topping', 'Mix.']],
    'a step with sub-steps becomes a section' => ['<ol><li>Select your brisket.<ol><li>Buy a whole brisket.</li></ol></li></ol>', ['# Select your brisket.', 'Buy a whole brisket.']],
    'a long bold line is not a heading' => ['<ul><li><strong>IMPORTANT: This recipe is not an easy one, and requires a lot of mental preparation. Read all notes.</strong></li></ul>', ['IMPORTANT: This recipe is not an easy one, and requires a lot of mental preparation. Read all notes.']],
    'paragraphs without a list' => ['<p>Mix.</p><p></p><p>Bake.</p>', ['Mix.', 'Bake.']],
]);
