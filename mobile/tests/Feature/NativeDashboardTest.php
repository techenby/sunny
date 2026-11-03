<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Recipe;
use App\NativeComponents\CreateInventoryItem;
use App\NativeComponents\CreateRecipe;
use App\NativeComponents\Inventory;
use App\NativeComponents\InventoryItemDetail;
use App\NativeComponents\Preferences;
use App\NativeComponents\RecipeDetail;
use App\NativeComponents\Recipes;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    $this->travelTo('2026-09-23 12:00:00');
});

beforeEach(function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
});

beforeEach(fn () => seedSunnyData());

it('renders the dashboard', function () {
    Native::visit('/dashboard')
        ->assertNavTitle('Summary')
        ->assertSee('Recent recipes')
        ->assertSee('Recent items')
        ->assertDontSee('Active team')
        ->assertElement('top_bar_action', fn (array $node): bool => ($node['props']['id'] ?? null) === 'preferences'
            && ($node['props']['label'] ?? null) === 'Preferences')
        ->assertMissingElement('top_bar_action', fn (array $node): bool => ($node['props']['id'] ?? null) === 'log-out')
        ->assertElement('refreshable', fn (array $node): bool => isset($node['props']['on_refresh']))
        ->assertAccessible();
});

it('summarizes the active team recipes and inventory', function () {
    Native::visit('/dashboard')
        ->assertSet('summary', ['recipes' => 6, 'items' => 9, 'locations' => 3, 'bins' => 3])
        ->assertSee('3 places · 3 storage spots');
});

it('opens preferences from the top bar', function () {
    Native::visit('/dashboard')
        ->tap('preferences')
        ->assertNavigatedTo('/preferences')
        ->follow()
        ->assertScreen(Preferences::class);
});

it('keeps the add menu above the tab bar', function () {
    Native::visit('/dashboard')
        ->assertElement('bottom_bar', fn (array $node): bool => str_contains(json_encode($node), '"ref":"dashboard-add"'))
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'dashboard-add'
            && ($node['props']['has_menu'] ?? false) === true
            && ($node['props']['a11y_label'] ?? null) === 'Add');
});

it('lists the most recently added or updated recipes', function () {
    Recipe::find(5)->update(['total_time' => null, 'photo_url' => 'https://sunny.example/oats.jpg']);

    $recipes = Native::visit('/dashboard')->get('recentRecipes');

    expect(array_slice($recipes, 0, 3))->toBe([
        ['id' => 1, 'name' => 'Buttermilk Pancakes', 'supporting' => '25 minutes', 'photo' => null, 'url' => '/recipes/1'],
        ['id' => 5, 'name' => 'Overnight Oats', 'supporting' => 'Added 4 days ago', 'photo' => 'https://sunny.example/oats.jpg', 'url' => '/recipes/5'],
        ['id' => 3, 'name' => 'Grandma’s Lasagna', 'supporting' => '1 hour 30 minutes', 'photo' => null, 'url' => '/recipes/3'],
    ]);
});

it('lists the most recently added or updated items', function () {
    Item::find(13)->update(['photo_url' => 'https://sunny.example/lights.jpg']);

    Native::visit('/dashboard')
        ->assertSet('recentItems', [
            ['id' => 3, 'name' => 'Canned tomatoes', 'type' => ItemType::Item, 'supporting' => 'Thing · Updated 17 hours ago', 'photo' => null, 'url' => '/inventory/3'],
            ['id' => 13, 'name' => 'String lights', 'type' => ItemType::Item, 'supporting' => 'Thing · Updated 3 days ago', 'photo' => 'https://sunny.example/lights.jpg', 'url' => '/inventory/13'],
            ['id' => 8, 'name' => 'Cordless drill', 'type' => ItemType::Item, 'supporting' => 'Thing · Updated 1 week ago', 'photo' => null, 'url' => '/inventory/8'],
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

it('switches to the tab for each section', function (string $ref, string $uri, string $screen) {
    Native::visit('/dashboard')
        ->tap($ref)
        ->assertReplacedWith($uri)
        ->follow()
        ->assertScreen($screen);
})->with([
    'recipes' => ['dashboard-recipes-all', '/recipes', Recipes::class],
    'inventory' => ['dashboard-inventory-all', '/inventory', Inventory::class],
]);

it('adds a new recipe or item from its section', function (string $ref, string $uri, string $screen) {
    Native::visit('/dashboard')
        ->tap($ref)
        ->assertNavigatedTo($uri)
        ->follow()
        ->assertScreen($screen);
})->with([
    'recipe' => ['New recipe', '/recipes/create', CreateRecipe::class],
    'item' => ['New item', '/inventory/create', CreateInventoryItem::class],
]);
