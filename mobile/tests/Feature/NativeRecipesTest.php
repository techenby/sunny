<?php

use App\Models\Recipe;
use App\NativeComponents\RecipeDetail;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('lists recipes alphabetically with their source and total time', function () {
    $screen = Native::visit('/recipes')
        ->assertNavTitle('Cookbook')
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

it('opens a recipe from the list', function () {
    Native::visit('/recipes')
        ->tap('Weeknight Chili')
        ->assertNavigatedTo('/recipes/2')
        ->follow()
        ->assertScreen(RecipeDetail::class)
        ->assertNavTitle('Weeknight Chili');
});

it('shows a recipe the way the web app does', function () {
    Native::visit('/recipes/1')
        ->assertNavTitle('Buttermilk Pancakes')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-description'
            && ($node['props']['headline'] ?? null) === 'Fluffy weekend pancakes that come together in one bowl.'
            && ($node['props']['supporting'] ?? null) === 'breakfast, weekend')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-source'
            && ($node['props']['supporting'] ?? null) === 'allrecipes.com')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Total time'
            && ($node['props']['trailing_value'] ?? null) === '25 minutes')
        ->assertSee('2 cups buttermilk')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['overline'] ?? null) === 'Step 3'
            && str_starts_with($node['props']['headline'] ?? '', 'Cook ¼ cup of batter'))
        ->assertSee('Let the batter rest for five minutes for taller pancakes.')
        ->assertAccessible();
});

it('skips blank recipe fields', function () {
    Native::visit('/recipes/5')
        ->assertDontSee('Cook time')
        ->assertDontSee('Source')
        ->assertMissingElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Notes')
        ->assertMissingElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Nutrition')
        ->assertMissingElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-description');
});

it('splits paragraph-formatted ingredients into rows', function () {
    Native::visit('/recipes/5')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === '1 cup rolled oats')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === '1 tbsp chia seeds')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['overline'] ?? null) === 'Step 1'
            && ($node['props']['headline'] ?? null) === 'Stir everything together in a jar, cover, and refrigerate overnight.');
});

it('opens a URL source in the in-app browser', function () {
    $bridge = Native::fakeBridge()->respondTo('Browser.OpenInApp', ['success' => true]);

    Native::visit('/recipes/2')->tap('recipe-source');

    $bridge->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://www.budgetbytes.com/weeknight-chili/');
});

it('shows a plain-text source without a link', function () {
    Native::visit('/recipes/3')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-source'
            && ($node['props']['trailing_value'] ?? null) === 'Grandma’s recipe card'
            && ! isset($node['props']['on_press']));
});

it('links a remix and its original recipe', function () {
    Native::visit('/recipes/3')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Remixes')
        ->tap('Veggie Lasagna')
        ->assertNavigatedTo('/recipes/4')
        ->follow()
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Remixed from')
        ->tap('Grandma’s Lasagna')
        ->assertNavigatedTo('/recipes/3');
});

it('explains when a recipe does not exist', function () {
    Native::visit('/recipes/999')
        ->assertNavTitle('Recipe')
        ->assertSee('This recipe could not be found.')
        ->assertMissingElement('list');
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
