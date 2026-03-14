<?php

use App\Actions\Recipes\ImportRecipeFromUrl;
use Illuminate\Support\Facades\Http;

function fakeRecipeHtml(array $schema): string
{
    $json = json_encode($schema);

    return <<<HTML
    <html><head>
    <script type="application/ld+json">{$json}</script>
    </head><body></body></html>
    HTML;
}

test('parses direct Recipe JSON-LD', function () {
    Http::fake([
        'example.com/*' => Http::response(fakeRecipeHtml([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Chocolate Cake',
            'description' => 'A <b>rich</b> chocolate cake.',
            'recipeYield' => '8 servings',
            'prepTime' => 'PT15M',
            'cookTime' => 'PT45M',
            'totalTime' => 'PT1H',
            'recipeIngredient' => ['2 cups flour', '1 cup sugar', '3 eggs'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Mix dry ingredients.'],
                ['@type' => 'HowToStep', 'text' => 'Add wet ingredients.'],
                ['@type' => 'HowToStep', 'text' => 'Bake at 350F for 45 minutes.'],
            ],
            'nutrition' => [
                '@type' => 'NutritionInformation',
                'calories' => '350 kcal',
                'fatContent' => '12g',
                'proteinContent' => '5g',
            ],
        ])),
    ]);

    $result = (new ImportRecipeFromUrl)->handle('https://example.com/chocolate-cake');

    expect($result)
        ->name->toBe('Chocolate Cake')
        ->source->toBe('https://example.com/chocolate-cake')
        ->servings->toBe('8 servings')
        ->prep_time->toBe('15m')
        ->cook_time->toBe('45m')
        ->total_time->toBe('1h')
        ->description->toBe('A rich chocolate cake.')
        ->ingredients->toBe('<ul><li>2 cups flour</li><li>1 cup sugar</li><li>3 eggs</li></ul>')
        ->instructions->toBe('<ol><li>Mix dry ingredients.</li><li>Add wet ingredients.</li><li>Bake at 350F for 45 minutes.</li></ol>')
        ->nutrition->toContain('Calories: 350 kcal')
        ->nutrition->toContain('Fat: 12g')
        ->nutrition->toContain('Protein: 5g');
});

test('parses Recipe from @graph array', function () {
    Http::fake([
        'example.com/*' => Http::response(fakeRecipeHtml([
            '@context' => 'https://schema.org',
            '@graph' => [
                ['@type' => 'WebSite', 'name' => 'Example'],
                ['@type' => 'WebPage', 'name' => 'Recipe Page'],
                [
                    '@type' => 'Recipe',
                    'name' => 'Applesauce',
                    'recipeYield' => '4 cups',
                    'prepTime' => 'PT10M',
                    'cookTime' => 'PT30M',
                    'totalTime' => 'PT40M',
                    'recipeIngredient' => ['6 apples', '1/2 cup water', '1/4 cup sugar'],
                    'recipeInstructions' => [
                        ['@type' => 'HowToStep', 'text' => 'Peel and chop apples.'],
                        ['@type' => 'HowToStep', 'text' => 'Simmer with water and sugar.'],
                    ],
                ],
            ],
        ])),
    ]);

    $result = (new ImportRecipeFromUrl)->handle('https://example.com/applesauce');

    expect($result)
        ->name->toBe('Applesauce')
        ->servings->toBe('4 cups')
        ->ingredients->toContain('6 apples');
});

test('converts ISO 8601 durations to human-readable format', function (string $input, string $expected) {
    expect((new ImportRecipeFromUrl)->formatDuration($input))->toBe($expected);
})->with([
    ['PT15M', '15m'],
    ['PT1H', '1h'],
    ['PT1H30M', '1h 30m'],
    ['PT2H', '2h'],
    ['PT45M', '45m'],
    ['P1DT2H', '1d 2h'],
    ['30 minutes', '30m'],
]);

test('throws exception when no recipe schema found', function () {
    Http::fake([
        'example.com/*' => Http::response('<html><body>No recipe here</body></html>'),
    ]);

    (new ImportRecipeFromUrl)->handle('https://example.com/no-recipe');
})->throws(RuntimeException::class, 'No recipe data found on this page.');

test('throws exception for failed HTTP request', function () {
    Http::fake([
        'example.com/*' => Http::response('Not Found', 404),
    ]);

    (new ImportRecipeFromUrl)->handle('https://example.com/missing');
})->throws(RuntimeException::class, 'Failed to fetch the URL.');

test('handles string instructions', function () {
    Http::fake([
        'example.com/*' => Http::response(fakeRecipeHtml([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Simple Recipe',
            'recipeInstructions' => ['Step one.', 'Step two.', 'Step three.'],
        ])),
    ]);

    $result = (new ImportRecipeFromUrl)->handle('https://example.com/simple');

    expect($result['instructions'])->toBe('<ol><li>Step one.</li><li>Step two.</li><li>Step three.</li></ol>');
});

test('handles array recipeYield', function () {
    Http::fake([
        'example.com/*' => Http::response(fakeRecipeHtml([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Yield Test',
            'recipeYield' => ['4', '4 servings'],
        ])),
    ]);

    $result = (new ImportRecipeFromUrl)->handle('https://example.com/yield');

    expect($result['servings'])->toBe('4');
});

test('handles HowToSection nested instructions', function () {
    Http::fake([
        'example.com/*' => Http::response(fakeRecipeHtml([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Applesauce',
            'recipeInstructions' => [
                [
                    '@type' => 'HowToSection',
                    'name' => 'Make Applesauce',
                    'itemListElement' => [
                        ['@type' => 'HowToStep', 'text' => 'Peel and chop apples.'],
                        ['@type' => 'HowToStep', 'text' => 'Simmer with water.'],
                    ],
                ],
                [
                    '@type' => 'HowToSection',
                    'name' => 'To Can Applesauce',
                    'itemListElement' => [
                        ['@type' => 'HowToStep', 'text' => 'Sterilize jars.'],
                        ['@type' => 'HowToStep', 'text' => 'Process in boiling water.'],
                    ],
                ],
            ],
        ])),
    ]);

    $result = (new ImportRecipeFromUrl)->handle('https://example.com/applesauce');

    expect($result['instructions'])
        ->toContain('<strong>Make Applesauce</strong>')
        ->toContain('<strong>To Can Applesauce</strong>')
        ->toContain('<li>Peel and chop apples.</li>')
        ->toContain('<li>Sterilize jars.</li>');
});

test('uses recipe url from schema when available', function () {
    Http::fake([
        'example.com/*' => Http::response(fakeRecipeHtml([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'URL Test',
            'url' => 'https://example.com/canonical-url',
        ])),
    ]);

    $result = (new ImportRecipeFromUrl)->handle('https://example.com/some-page');

    expect($result['source'])->toBe('https://example.com/canonical-url');
});
