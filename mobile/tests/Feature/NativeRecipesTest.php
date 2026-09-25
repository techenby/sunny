<?php

use App\Models\Recipe;
use App\NativeComponents\RecipeDetail;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('lists recipes alphabetically with their source and total time', function () {
    $screen = Native::visit('/recipes')
        ->assertNavTitle('Recipes')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Weeknight Chili'
            && ($node['props']['supporting'] ?? null) === 'budgetbytes.com · 45 minutes')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Grandma’s Lasagna'
            && ($node['props']['supporting'] ?? null) === 'Grandma’s recipe card · 1 hour 30 minutes')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Overnight Oats'
            && ($node['props']['supporting'] ?? null) === '8 hours')
        ->assertAccessible();

    $headlines = [];
    $collect = function (array $node) use (&$collect, &$headlines): void {
        if (($node['type'] ?? null) === 'list_item') {
            $headlines[] = $node['props']['headline'];
        }

        foreach ($node['children'] ?? [] as $child) {
            $collect($child);
        }
    };
    $collect($screen->tree());

    expect($headlines)->toBe([
        'Buttermilk Pancakes',
        'Chocolate Chip Cookies',
        'Grandma’s Lasagna',
        'Overnight Oats',
        'Veggie Lasagna',
        'Weeknight Chili',
    ]);
});

it('filters recipes by name as you search', function () {
    Native::visit('/recipes')
        ->input('updateSearch', ' LASAGNA ')
        ->assertSee('Grandma’s Lasagna')
        ->assertSee('Veggie Lasagna')
        ->assertDontSee('Weeknight Chili')
        ->input('updateSearch', 'tacos')
        ->assertSee('No recipes match “tacos”')
        ->input('updateSearch', '')
        ->assertSee('Weeknight Chili');
});

it('opens a recipe from the list', function () {
    Native::visit('/recipes')
        ->tap('Weeknight Chili')
        ->assertNavigatedTo('/recipes/2')
        ->follow()
        ->assertScreen(RecipeDetail::class)
        ->assertSee('Weeknight Chili');
});

it('shows a recipe the way the web app does', function () {
    Native::visit('/recipes/1')
        ->assertNavTitle('Buttermilk Pancakes')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-name'
            && ($node['props']['text'] ?? null) === 'Buttermilk Pancakes')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-description'
            && ($node['props']['text'] ?? null) === 'Fluffy weekend pancakes that come together in one bowl.')
        ->assertSee('breakfast')
        ->assertSee('weekend')
        ->assertSee('allrecipes.com')
        ->assertSee('Total time')
        ->assertSee('25 minutes')
        ->assertSee('2 cups buttermilk')
        ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'step-3')
        ->assertSee('Let the batter rest for five minutes for taller pancakes.')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-add-steps')
        ->assertAccessible();
});

it('ticks ingredients off while cooking', function () {
    Native::visit('/recipes/1')
        ->tap('ingredient-1')
        ->assertSet('checkedIngredients', [1])
        ->assertSee('1 of')
        ->tap('ingredient-0')
        ->tap('ingredient-1')
        ->assertSet('checkedIngredients', [0]);
});

it('skips blank recipe fields', function () {
    Native::visit('/recipes/5')
        ->assertDontSee('Cook time')
        ->assertDontSee('Source')
        ->assertDontSee('Notes')
        ->assertDontSee('Nutrition')
        ->assertMissingElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-description');
});

it('splits paragraph-formatted ingredients into rows', function () {
    Native::visit('/recipes/5')
        ->assertSee('1 cup rolled oats')
        ->assertSee('1 tbsp chia seeds')
        ->assertElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'step-1')
        ->assertSee('Stir everything together in a jar, cover, and refrigerate overnight.');
});

it('offers to add ingredients and steps when a recipe has none', function () {
    Recipe::find(5)->update(['ingredients' => null, 'instructions' => null]);

    Native::visit('/recipes/5')
        ->tap('recipe-add-steps')
        ->assertNavigatedTo('/recipes/5/edit');
});

it('opens a URL source in the in-app browser', function () {
    $bridge = Native::fakeBridge()->respondTo('Browser.OpenInApp', ['success' => true]);

    Native::visit('/recipes/2')->tap('recipe-source');

    $bridge->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://www.budgetbytes.com/weeknight-chili/');
});

it('shows a plain-text source without a link', function () {
    Native::visit('/recipes/3')
        ->assertSee('Grandma’s recipe card')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-source')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-source');
});

it('links a remix and its original recipe', function () {
    Native::visit('/recipes/3')
        ->assertSee('Remixes')
        ->tap('related-recipe-4')
        ->assertNavigatedTo('/recipes/4')
        ->follow()
        ->assertSee('Remixed from')
        ->tap('related-recipe-3')
        ->assertNavigatedTo('/recipes/3');
});

it('explains when a recipe does not exist', function () {
    Native::visit('/recipes/999')
        ->assertNavTitle('Recipe')
        ->assertSee('This recipe could not be found.')
        ->assertMissingElement('scroll_view');
});

it('shows the synced recipe photo with an accessible description', function () {
    $recipe = Recipe::findOrFail(1);
    $recipe->update(['photo_url' => 'https://sunny.example/photos/1.jpg']);

    Native::visit('/recipes/1')
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'https://sunny.example/photos/1.jpg')
        ->assertAccessible();
});

it('omits the photo when the recipe has none', function () {
    Native::visit('/recipes/1')->assertMissingElement('image');
});
