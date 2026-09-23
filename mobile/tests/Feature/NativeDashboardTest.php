<?php

use App\Enums\ItemType;
use App\NativeComponents\CreateInventoryItem;
use App\NativeComponents\CreateRecipe;
use App\NativeComponents\Home;
use App\NativeComponents\Inventory;
use App\NativeComponents\InventoryItemDetail;
use App\NativeComponents\RecipeDetail;
use App\NativeComponents\Recipes;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    $this->travelTo('2026-09-23 12:00:00');
});

beforeEach(function () {
    config(['services.sunny.api_url' => 'https://sunny.example/api']);
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
});

it('renders the dashboard', function () {
    Native::visit('/dashboard')
        ->assertNavTitle('Dashboard')
        ->assertSee('Your household at a glance')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Recent recipes')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Recent items')
        ->assertSee('Teams')
        ->assertElement('top_bar_action', fn (array $node): bool => ($node['props']['id'] ?? null) === 'log-out'
            && ($node['props']['label'] ?? null) === 'Log out')
        ->assertAccessible();
});

it('returns to the home screen when logging out', function () {
    Native::visit('/dashboard')
        ->tap('Log out')
        ->assertReplacedWith('/')
        ->follow()
        ->assertScreen(Home::class);
});

it('lists the most recently added or updated recipes', function () {
    Native::visit('/dashboard')
        ->assertSet('recentRecipes', [
            ['id' => 1, 'name' => 'Buttermilk Pancakes', 'supporting' => 'Updated 2 days ago', 'url' => '/recipes/1'],
            ['id' => 5, 'name' => 'Overnight Oats', 'supporting' => 'Added 4 days ago', 'url' => '/recipes/5'],
            ['id' => 3, 'name' => 'Grandma’s Lasagna', 'supporting' => 'Updated 3 weeks ago', 'url' => '/recipes/3'],
        ]);
});

it('lists the most recently added or updated items', function () {
    Native::visit('/dashboard')
        ->assertSet('recentItems', [
            ['id' => 3, 'name' => 'Canned tomatoes', 'type' => ItemType::Item, 'supporting' => 'Item · Updated 17 hours ago', 'url' => '/inventory/3'],
            ['id' => 13, 'name' => 'String lights', 'type' => ItemType::Item, 'supporting' => 'Item · Updated 3 days ago', 'url' => '/inventory/13'],
            ['id' => 8, 'name' => 'Cordless drill', 'type' => ItemType::Item, 'supporting' => 'Item · Updated 1 week ago', 'url' => '/inventory/8'],
        ]);
});

it('opens a recent entry from its section', function (string $ref, string $uri, string $screen) {
    Native::visit('/dashboard')
        ->tap($ref)
        ->assertNavigatedTo($uri)
        ->follow()
        ->assertScreen($screen);
})->with([
    'recipe' => ['dashboard-recipes-1', '/recipes/1', RecipeDetail::class],
    'item' => ['dashboard-inventory-3', '/inventory/3', InventoryItemDetail::class],
]);

it('opens the full list from each section', function (string $ref, string $uri, string $screen) {
    Native::visit('/dashboard')
        ->tap($ref)
        ->assertNavigatedTo($uri)
        ->follow()
        ->assertScreen($screen);
})->with([
    'cookbook' => ['dashboard-recipes-all', '/recipes', Recipes::class],
    'inventory' => ['dashboard-inventory-all', '/inventory', Inventory::class],
]);

it('adds a new recipe or item from its section', function (string $ref, string $uri, string $screen) {
    Native::visit('/dashboard')
        ->tap($ref)
        ->assertNavigatedTo($uri)
        ->follow()
        ->assertScreen($screen);
})->with([
    'recipe' => ['dashboard-recipes-create', '/recipes/create', CreateRecipe::class],
    'item' => ['dashboard-inventory-create', '/inventory/create', CreateInventoryItem::class],
]);
