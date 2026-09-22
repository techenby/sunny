<?php

use App\NativeComponents\InventoryItemDetail;
use Native\Mobile\Testing\Native;

it('lists inventory grouped by location', function () {
    Native::visit('/inventory')
        ->assertNavTitle('Inventory')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Pantry'
            && ($node['props']['footer'] ?? null) === '3 items')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Garage')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['header'] ?? null) === 'Basement')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Paper towels'
            && ($node['props']['supporting'] ?? null) === 'Shelf B'
            && ($node['props']['trailing_type'] ?? null) === 'text'
            && ($node['props']['trailing_value'] ?? null) === '12')
        ->assertAccessible();
});

it('shows a platform icon for each location', function (string $platform, string $iconName) {
    Native::visit('/inventory', platform: $platform)
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Cordless drill'
            && ($node['props']['leading_icon'] ?? null) === $iconName);
})->with([
    'ios' => ['ios', 'wrench'],
    'android' => ['android', 'garage'],
]);

it('opens an item from the list', function () {
    Native::visit('/inventory')
        ->tap('Camping tent')
        ->assertNavigatedTo('/inventory/6')
        ->follow()
        ->assertScreen(InventoryItemDetail::class)
        ->assertNavTitle('Camping tent');
});

it('shows an item’s location, quantity, and notes', function () {
    Native::visit('/inventory/8')
        ->assertNavTitle('Paper towels')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Location'
            && ($node['props']['trailing_value'] ?? null) === 'Basement')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Spot'
            && ($node['props']['trailing_value'] ?? null) === 'Shelf B')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Quantity'
            && ($node['props']['trailing_value'] ?? null) === '12')
        ->assertSee('Bulk pack.')
        ->assertAccessible();
});

it('explains when an item does not exist', function () {
    Native::visit('/inventory/999')
        ->assertNavTitle('Item')
        ->assertSee('This item could not be found.')
        ->assertMissingElement('list');
});
