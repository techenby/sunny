<?php

use App\Models\Item;
use App\NativeComponents\InventoryItemDetail;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('lists the top-level locations', function () {
    Native::visit('/inventory')
        ->assertNavTitle('Inventory')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Garage'
            && ($node['props']['supporting'] ?? null) === 'Location · 2 items'
            && ($node['props']['leading_icon_color'] ?? null) !== null)
        ->assertSee('Basement')
        ->assertSee('Kitchen')
        ->assertDontSee('Cordless drill')
        ->assertAccessible();
});

it('shows a platform icon for each item type', function (string $platform, string $path, string $iconName) {
    Native::visit($path, platform: $platform)
        ->assertElement('icon', fn (array $node): bool => ($node['props']['name'] ?? null) === $iconName
            && (float) ($node['props']['size'] ?? 0) === 14.0);
})->with([
    'ios location' => ['ios', '/inventory/6', 'mappin'],
    'ios bin' => ['ios', '/inventory/7', 'archivebox'],
    'ios item' => ['ios', '/inventory/8', 'cube'],
    'android location' => ['android', '/inventory/6', 'place'],
    'android bin' => ['android', '/inventory/7', 'inventory_2'],
    'android item' => ['android', '/inventory/8', 'view_in_ar'],
]);

it('searches nested items and says where each one lives', function () {
    Native::visit('/inventory')
        ->input('updateSearch', 'light')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'String lights'
            && ($node['props']['supporting'] ?? null) === 'Item · in Holiday decorations')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Spare light bulbs'
            && ($node['props']['supporting'] ?? null) === 'Item · in Basement')
        ->assertDontSee('Kitchen')
        ->tap('String lights')
        ->assertNavigatedTo('/inventory/13');

    Native::visit('/inventory')
        ->input('updateSearch', 'garage')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Garage'
            && ($node['props']['supporting'] ?? null) === 'Location')
        ->input('updateSearch', 'zzz')
        ->assertSee('No items match “zzz”');
});

it('drills into a location’s contents', function () {
    Native::visit('/inventory')
        ->tap('Garage')
        ->assertNavigatedTo('/inventory/6')
        ->follow()
        ->assertScreen(InventoryItemDetail::class)
        ->assertNavTitle('Garage')
        ->assertSee('Garage')
        ->assertSee('Contents')
        ->assertSee('2 items')
        ->assertSee('Bin · 2 items')
        ->tap('item-child-7')
        ->assertNavigatedTo('/inventory/7');
});

it('shows an item’s type, path, and metadata', function () {
    Native::visit('/inventory/8')
        ->assertNavTitle('Cordless drill')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'item-summary'
            && trim($node['props']['text'] ?? '') === 'Item · in Tool chest')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-ancestor-6')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-parent')
        ->assertSee('Brand')
        ->assertSee('DeWalt')
        ->assertSee('Model')
        ->assertSee('DCD771')
        ->assertDontSee('Contents')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-add-child')
        ->assertAccessible();
});

it('jumps to any container in the path', function () {
    Native::visit('/inventory/8')
        ->tap('item-ancestor-6')
        ->assertNavigatedTo('/inventory/6');
});

it('adds an item inside a location or bin', function () {
    Native::visit('/inventory/7')
        ->tap('item-add-child')
        ->assertNavigatedTo('/inventory/create')
        ->follow()
        ->assertSet('parentName', 'Tool chest')
        ->assertSet('typeIndex', 2);
});

it('navigates up to the containing item when it is not already on the stack', function () {
    Native::visit('/inventory/8')
        ->tap('item-parent')
        ->assertNavigatedTo('/inventory/7');
});

it('pops back to the containing item instead of pushing a duplicate of it', function () {
    Native::visit('/inventory')
        ->tap('Garage')
        ->follow()
        ->tap('item-child-10')
        ->follow()
        ->assertSee('Camping tent')
        ->tap('item-parent')
        ->assertWentBack()
        ->goBack()
        ->assertSee('Garage')
        ->pressBack()
        ->assertWentBack();
});

it('omits the parent and metadata sections when there are none', function () {
    Native::visit('/inventory/1')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-parent')
        ->assertDontSee('Where it is')
        ->assertDontSee('Details');
});

it('explains when an item does not exist', function () {
    Native::visit('/inventory/999')
        ->assertNavTitle('Item')
        ->assertSee('This item could not be found.')
        ->assertMissingElement('scroll_view');
});

it('shows the synced item photo with an accessible description', function () {
    $item = Item::findOrFail(1);
    $item->update(['photo_url' => 'https://sunny.example/photos/1.jpg']);

    Native::visit('/inventory/1')
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'https://sunny.example/photos/1.jpg')
        ->assertAccessible();
});

it('omits the photo when the item has none', function () {
    Native::visit('/inventory/1')->assertMissingElement('image');
});
