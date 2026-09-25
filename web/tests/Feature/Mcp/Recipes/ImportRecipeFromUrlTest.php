<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Recipes\ImportRecipeFromUrl;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('it imports a recipe from a url and saves it to the current team', function () {
    Http::fake([
        'example.com/*' => Http::response(fakeRecipeHtml([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Chocolate Cake',
            'recipeYield' => '8 servings',
            'prepTime' => 'PT15M',
            'recipeIngredient' => ['2 cups flour', '1 cup sugar'],
            'recipeInstructions' => [
                ['@type' => 'HowToStep', 'text' => 'Mix dry ingredients.'],
                ['@type' => 'HowToStep', 'text' => 'Bake at 350F.'],
            ],
        ])),
    ]);

    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ImportRecipeFromUrl::class, ['url' => 'https://example.com/chocolate-cake'])
        ->assertOk()
        ->assertSee('Chocolate Cake');

    expect(Recipe::sole())
        ->team_id->toBe($user->current_team_id)
        ->name->toBe('Chocolate Cake')
        ->source->toBe('https://example.com/chocolate-cake')
        ->servings->toBe('8 servings')
        ->ingredients->toBe('<ul><li>2 cups flour</li><li>1 cup sugar</li></ul>')
        ->instructions->toBe('<ol><li>Mix dry ingredients.</li><li>Bake at 350F.</li></ol>');
});

test('it returns an error when the url cannot be fetched', function () {
    Http::fake(['example.com/*' => Http::response('Not Found', 404)]);

    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ImportRecipeFromUrl::class, ['url' => 'https://example.com/missing'])
        ->assertHasErrors(['Failed to fetch the URL. Please check the URL and try again.']);

    expect(Recipe::count())->toBe(0);
});

test('it returns an error when the page has no recipe data', function () {
    Http::fake(['example.com/*' => Http::response('<html><body>No recipes here.</body></html>')]);

    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ImportRecipeFromUrl::class, ['url' => 'https://example.com/blog-post'])
        ->assertHasErrors(['No recipe data found on this page.']);

    expect(Recipe::count())->toBe(0);
});

test('it requires a valid url', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ImportRecipeFromUrl::class, ['url' => 'not-a-url'])
        ->assertHasErrors();
});
