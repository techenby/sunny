<?php

use App\NativeComponents\CreateRecipe;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Testing\Native;

it('opens the create screen from the cookbook', function () {
    Native::visit('/recipes')
        ->tap('recipes-create')
        ->assertNavigatedTo('/recipes/create')
        ->follow()
        ->assertScreen(CreateRecipe::class)
        ->assertNavTitle('New recipe');
});

it('renders every field the web recipe form has', function () {
    Native::visit('/recipes/create')
        ->assertSee('Name')
        ->assertSee('Source (URL or text)')
        ->assertSee('Photo')
        ->assertSee('Tags')
        ->assertSee('Servings')
        ->assertSee('Prep time')
        ->assertSee('Cook time')
        ->assertSee('Total time')
        ->assertSee('Description')
        ->assertSee('Ingredients')
        ->assertSee('Instructions')
        ->assertSee('Notes')
        ->assertSee('Nutrition')
        ->assertSee('Save recipe')
        ->assertAccessible();
});

it('binds the text fields', function () {
    Native::visit('/recipes/create')
        ->input('recipe-name', 'Buttermilk Pancakes')
        ->input('recipe-source', 'https://example.com/pancakes')
        ->input('recipe-servings', '4')
        ->input('recipe-prep-time', '10 minutes')
        ->input('recipe-cook-time', '15 minutes')
        ->input('recipe-total-time', '25 minutes')
        ->input('recipe-description', 'Fluffy weekend pancakes.')
        ->input('recipe-notes', 'Rest the batter.')
        ->input('recipe-nutrition', "Calories: 410\nProtein: 28 g")
        ->assertSet('name', 'Buttermilk Pancakes')
        ->assertSet('source', 'https://example.com/pancakes')
        ->assertSet('servings', '4')
        ->assertSet('prepTime', '10 minutes')
        ->assertSet('cookTime', '15 minutes')
        ->assertSet('totalTime', '25 minutes')
        ->assertSet('description', 'Fluffy weekend pancakes.')
        ->assertSet('notes', 'Rest the batter.')
        ->assertSet('nutrition', "Calories: 410\nProtein: 28 g");
});

it('offers the web form’s tag vocabulary as chips', function () {
    $harness = Native::visit('/recipes/create');

    foreach (CreateRecipe::TAG_OPTIONS as $tag) {
        $harness->assertElement('chip', fn (array $node): bool => ($node['props']['label'] ?? null) === $tag);
    }

    expect(CreateRecipe::TAG_OPTIONS)->toHaveCount(12);
});

it('packs the tag chips into rows that fit, since the renderer never wraps', function () {
    $harness = Native::visit('/recipes/create');

    $rows = $harness->get('tagRows');

    // Every tag appears exactly once, in order.
    expect(array_merge(...$rows))->toBe(CreateRecipe::TAG_OPTIONS);

    // No row can overflow the width budget the packer is built around.
    foreach ($rows as $row) {
        $width = array_sum(array_map(fn (string $tag): int => mb_strlen($tag) + 5, $row));
        expect($width)->toBeLessThanOrEqual(42);
    }

    // A single unwrapped row of 12 chips is what squeezed them to slivers.
    expect(count($rows))->toBeGreaterThan(1);
});

it('renders one row element per packed tag row', function () {
    $harness = Native::visit('/recipes/create');
    $expected = count($harness->get('tagRows'));

    $chipRows = 0;
    $walk = function (array $node) use (&$walk, &$chipRows): void {
        $kids = $node['children'] ?? [];
        if (($node['type'] ?? '') === 'row' && ($kids[0]['type'] ?? null) === 'chip') {
            $chipRows++;
        }
        foreach ($kids as $child) {
            $walk($child);
        }
    };
    $walk($harness->tree());

    expect($chipRows)->toBe($expected);
});

it('adds and removes tags as chips are toggled', function () {
    Native::visit('/recipes/create')
        ->toggle('recipe-tag-breakfast', true)
        ->toggle('recipe-tag-vegetarian', true)
        ->assertSet('tags', ['Breakfast', 'Vegetarian'])
        ->assertElement('chip', fn (array $node): bool => ($node['props']['label'] ?? null) === 'Breakfast'
            && ($node['props']['value'] ?? null) === true)
        ->toggle('recipe-tag-breakfast', false)
        ->assertSet('tags', ['Vegetarian']);
});

it('keeps multi-word tags intact', function () {
    Native::visit('/recipes/create')
        ->toggle('recipe-tag-slow-cooker', true)
        ->assertSet('tags', ['Slow Cooker']);
});

it('turns the ingredient and instruction lines into the stored html', function () {
    Native::visit('/recipes/create')
        ->input('recipe-ingredients', "2 cups flour\n\n2 eggs  \n")
        ->input('recipe-instructions', "Whisk the dry ingredients.\nCook on a hot griddle.")
        ->assertSet('ingredientsHtml', '<ul><li>2 cups flour</li><li>2 eggs</li></ul>')
        ->assertSet('instructionsHtml', '<ol><li>Whisk the dry ingredients.</li><li>Cook on a hot griddle.</li></ol>');
});

it('leaves the ingredients and instructions null when blank', function () {
    Native::visit('/recipes/create')
        ->assertSet('ingredientsHtml', null)
        ->input('recipe-ingredients', "  \n  ")
        ->assertSet('ingredientsHtml', null);
});

it('escapes html typed into an ingredient line', function () {
    Native::visit('/recipes/create')
        ->input('recipe-ingredients', '1 tbsp <b>butter</b>')
        ->assertSet('ingredientsHtml', '<ul><li>1 tbsp &lt;b&gt;butter&lt;/b&gt;</li></ul>');
});

it('captures a photo for the recipe', function () {
    Native::fakeBridge();

    Native::visit('/recipes/create')
        ->tap('create-recipe-photo-camera')
        ->assertNativeCalled('Camera.GetPhoto')
        ->emitNative(PhotoTaken::class, ['path' => '/tmp/captured.jpg'])
        ->assertSet('photoPath', '/tmp/captured.jpg')
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === '/tmp/captured.jpg')
        ->tap('create-recipe-photo-remove')
        ->assertSet('photoPath', null);
});

it('refuses to save a recipe without a name', function () {
    Native::visit('/recipes/create')
        ->input('recipe-name', '   ')
        ->tap('create-recipe-submit')
        ->assertNoNavigation()
        ->assertSee('Give the recipe a name.');
});

it('enforces the web form’s length limits', function (string $property, int $max, string $message) {
    Native::visit('/recipes/create')
        ->set('name', 'Buttermilk Pancakes')
        ->set($property, str_repeat('a', $max + 1))
        ->tap('create-recipe-submit')
        ->assertNoNavigation()
        ->assertSee($message);
})->with([
    'name' => ['name', 255, 'The name is too long (255 characters max).'],
    'source' => ['source', 500, 'The source is too long (500 characters max).'],
    'servings' => ['servings', 50, 'Servings is too long (50 characters max).'],
    'prep time' => ['prepTime', 50, 'Prep time is too long (50 characters max).'],
    'cook time' => ['cookTime', 50, 'Cook time is too long (50 characters max).'],
    'total time' => ['totalTime', 50, 'Total time is too long (50 characters max).'],
]);

it('returns to the cookbook on save until the recipe API is integrated', function () {
    Native::visit('/recipes/create')
        ->input('recipe-name', 'Buttermilk Pancakes')
        ->tap('create-recipe-submit')
        ->assertWentBack();
});

it('gives every tappable control at least a 48dp touch target', function () {
    $undersized = [];

    $walk = function (array $node) use (&$walk, &$undersized): void {
        if (in_array($node['type'] ?? '', ['button', 'pressable'], true)) {
            $height = $node['layout']['height'] ?? null;
            $tallEnough = $height === null
                ? ($node['props']['size'] ?? null) === 'lg'
                : $height >= 48;

            if (! $tallEnough) {
                $undersized[] = $node['ref'] ?? $node['type'];
            }
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk(Native::visit('/recipes/create')->set('photoPath', '/tmp/captured.jpg')->tree());

    expect($undersized)->toBe([]);
});
