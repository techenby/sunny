<?php

use App\NativeComponents\Home;
use App\NativeComponents\Inventory;
use App\NativeComponents\Recipes;
use Native\Mobile\Testing\Native;

it('renders the dashboard', function () {
    Native::visit('/dashboard')
        ->assertNavTitle('Dashboard')
        ->assertSee('Your household at a glance')
        ->assertSee('Cookbook')
        ->assertSee('Inventory')
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

it('opens the cookbook and inventory from their cards', function (string $card, string $uri, string $screen) {
    Native::visit('/dashboard')
        ->tap($card)
        ->assertNavigatedTo($uri)
        ->follow()
        ->assertScreen($screen);
})->with([
    'cookbook' => ['dashboard-recipes', '/recipes', Recipes::class],
    'inventory' => ['dashboard-inventory', '/inventory', Inventory::class],
]);

it('marks only the tappable cards with a chevron', function () {
    $chevrons = 0;
    $countChevrons = function (array $node) use (&$countChevrons, &$chevrons): void {
        if (($node['type'] ?? null) === 'icon' && ($node['props']['name'] ?? null) === 'chevron.right') {
            $chevrons++;
        }

        foreach ($node['children'] ?? [] as $child) {
            $countChevrons($child);
        }
    };

    $countChevrons(Native::visit('/dashboard', platform: 'ios')->tree());

    expect($chevrons)->toBe(2);
});
