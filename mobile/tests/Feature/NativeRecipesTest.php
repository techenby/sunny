<?php

use App\NativeComponents\RecipeDetail;
use Native\Mobile\Testing\Native;

it('lists recipes grouped by course', function () {
    Native::visit('/recipes')
        ->assertNavTitle('Recipes')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Breakfast'
            && ($node['props']['footer'] ?? null) === '3 recipes')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Dinner'
            && ($node['props']['footer'] ?? null) === '4 recipes')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Dessert'
            && ($node['props']['footer'] ?? null) === '2 recipes')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Buttermilk Pancakes'
            && ($node['props']['supporting'] ?? null) === 'Serves 4 · 25 min')
        ->assertAccessible();
});

it('shows a platform icon for each course', function (string $platform, string $iconName) {
    Native::visit('/recipes', platform: $platform)
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Weeknight Chili'
            && ($node['props']['leading_icon'] ?? null) === $iconName);
})->with([
    'ios' => ['ios', 'fork.knife'],
    'android' => ['android', 'dinner_dining'],
]);

it('opens a recipe from the list', function () {
    Native::visit('/recipes')
        ->tap('Weeknight Chili')
        ->assertNavigatedTo('/recipes/4')
        ->follow()
        ->assertScreen(RecipeDetail::class)
        ->assertNavTitle('Weeknight Chili');
});

it('shows a recipe’s details, ingredients, and steps', function () {
    Native::visit('/recipes/1')
        ->assertNavTitle('Buttermilk Pancakes')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Prep time'
            && ($node['props']['trailing_value'] ?? null) === '10 min')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Cook time'
            && ($node['props']['trailing_value'] ?? null) === '15 min')
        ->assertSee('2 cups buttermilk')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['overline'] ?? null) === 'Step 3'
            && str_starts_with($node['props']['headline'] ?? '', 'Cook ¼ cup of batter'))
        ->assertAccessible();
});

it('explains when a recipe does not exist', function () {
    Native::visit('/recipes/999')
        ->assertNavTitle('Recipe')
        ->assertSee('This recipe could not be found.')
        ->assertMissingElement('list');
});
