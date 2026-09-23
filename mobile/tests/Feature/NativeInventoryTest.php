<?php

use App\NativeComponents\InventoryItemDetail;
use Native\Mobile\Testing\Native;

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

it('shows a platform icon for each item type', function (string $platform, string $path, string $headline, string $iconName) {
    Native::visit($path, platform: $platform)
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === $headline
            && ($node['props']['leading_icon'] ?? null) === $iconName);
})->with([
    'ios location' => ['ios', '/inventory', 'Garage', 'mappin'],
    'ios bin' => ['ios', '/inventory/6', 'Tool chest', 'archivebox'],
    'ios item' => ['ios', '/inventory/6', 'Camping tent', 'cube'],
    'android location' => ['android', '/inventory', 'Garage', 'place'],
    'android bin' => ['android', '/inventory/6', 'Tool chest', 'inventory_2'],
    'android item' => ['android', '/inventory/6', 'Camping tent', 'view_in_ar'],
]);

it('drills into a location’s contents', function () {
    Native::visit('/inventory')
        ->tap('Garage')
        ->assertNavigatedTo('/inventory/6')
        ->follow()
        ->assertScreen(InventoryItemDetail::class)
        ->assertNavTitle('Garage')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Contents'
            && ($node['props']['footer'] ?? null) === '2 items')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Tool chest'
            && ($node['props']['supporting'] ?? null) === 'Bin · 2 items')
        ->tap('Tool chest')
        ->assertNavigatedTo('/inventory/7');
});

it('shows an item’s type, parent, and metadata', function () {
    Native::visit('/inventory/8')
        ->assertNavTitle('Cordless drill')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Type'
            && ($node['props']['trailing_value'] ?? null) === 'Item')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'item-parent'
            && ($node['props']['headline'] ?? null) === 'Tool chest')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Brand'
            && ($node['props']['trailing_value'] ?? null) === 'DeWalt')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Model'
            && ($node['props']['trailing_value'] ?? null) === 'DCD771')
        ->assertMissingElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Contents')
        ->assertAccessible();
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
        ->tap('Camping tent')
        ->follow()
        ->assertNavTitle('Camping tent')
        ->tap('item-parent')
        ->assertWentBack()
        ->goBack()
        ->assertNavTitle('Garage')
        ->pressBack()
        ->assertWentBack();
});

it('omits the parent and metadata sections when there are none', function () {
    Native::visit('/inventory/1')
        ->assertMissingElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'item-parent')
        ->assertMissingElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Metadata');
});

it('explains when an item does not exist', function () {
    Native::visit('/inventory/999')
        ->assertNavTitle('Item')
        ->assertSee('This item could not be found.')
        ->assertMissingElement('list');
});
