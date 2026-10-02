<?php

use Native\Mobile\Testing\Native;

beforeEach(function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
});

beforeEach(fn () => seedSunnyData());

it('shows the summary, routines, inventory, recipes and lists tabs', function (string $uri, string $activeTab) {
    Native::visit($uri)
        ->assertHasTabBar()
        ->assertHasTab('Summary')
        ->assertHasTab('Routines')
        ->assertHasTab('Inventory')
        ->assertHasTab('Recipes')
        ->assertHasTab('Lists')
        ->assertTabActive($activeTab);
})->with([
    'summary' => ['/dashboard', 'Summary'],
    'routines' => ['/today', 'Routines'],
    'inventory' => ['/inventory', 'Inventory'],
    'recipes' => ['/recipes', 'Recipes'],
    'item detail' => ['/inventory/3', 'Inventory'],
    'recipe detail' => ['/recipes/1', 'Recipes'],
    'lists' => ['/lists', 'Lists'],
    'list detail' => ['/lists/1', 'Lists'],
]);

it('leaves the signed-out screens without tabs', function (string $uri) {
    Native::visit($uri)->assertMissingElement('native_root_tabs');
})->with(['/login', '/register']);
