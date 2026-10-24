<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\NativeComponents\Inventory;
use App\NativeComponents\InventoryItemDetail;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('lists the places in the home and what is inside each', function () {
    Native::visit('/inventory')
        ->assertNavTitle('Inventory')
        ->assertSee('Places in your home')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'inventory-item-6')
        ->assertSee('2 things inside')
        ->assertSee('1 thing inside')
        ->assertDontSee('Not in a place yet')
        ->assertDontSee('Cordless drill')
        ->assertAccessible();
});

it('sets apart top-level things that are not in a place yet', function () {
    Item::findOrFail(12)->update(['parent_id' => null]);

    $screen = Native::visit('/inventory')
        ->assertSee('Not in a place yet')
        ->assertSee('Open one and tap Move to put it away.');

    expect(array_column($screen->get('places'), 'name'))->toBe(['Basement', 'Garage', 'Kitchen'])
        ->and(array_column($screen->get('unplaced'), 'name'))->toBe(['Holiday decorations']);
});

it('shows a platform icon for each item type', function (string $platform, string $path, string $iconName) {
    Native::visit($path, platform: $platform)
        ->assertElement('icon', fn (array $node): bool => ($node['props']['name'] ?? null) === $iconName
            && (float) ($node['props']['size'] ?? 0) === 16.0);
})->with([
    'ios location' => ['ios', '/inventory/6', 'mappin'],
    'ios bin' => ['ios', '/inventory/7', 'archivebox'],
    'ios item' => ['ios', '/inventory/8', 'cube'],
    'android location' => ['android', '/inventory/6', 'place'],
    'android bin' => ['android', '/inventory/7', 'inventory_2'],
    'android item' => ['android', '/inventory/8', 'view_in_ar'],
]);

it('shows an item’s photo in place of its type icon', function () {
    Item::findOrFail(6)->update(['photo_url' => 'https://sunny.example/garage.jpg']);
    Item::findOrFail(7)->update(['photo_url' => 'https://sunny.example/bin.jpg']);

    Native::visit('/inventory')
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'https://sunny.example/garage.jpg')
        ->assertMissingElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'https://sunny.example/bin.jpg')
        ->input('updateSearch', 'garage')
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'https://sunny.example/garage.jpg');

    Native::visit('/inventory/6')
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'https://sunny.example/bin.jpg');
});

it('searches nested items and shows the full path to each one', function () {
    Native::visit('/inventory')
        ->input('updateSearch', 'light')
        ->assertSee('In Basement › Holiday decorations')
        ->assertSee('In Basement')
        ->assertDontSee('Kitchen')
        ->tap('String lights')
        ->assertNavigatedTo('/inventory/13');

    Native::visit('/inventory')
        ->input('updateSearch', 'garage')
        ->assertSee('Place')
        ->input('updateSearch', 'zzz')
        ->assertSee('Nothing called “zzz”');
});

it('says when a search result is not in a place yet', function () {
    Item::findOrFail(12)->update(['parent_id' => null]);

    Native::visit('/inventory')
        ->input('updateSearch', 'holiday')
        ->assertSee('Not in a place yet');
});

it('drills into a location’s contents', function () {
    Native::visit('/inventory')
        ->tap('Garage')
        ->assertNavigatedTo('/inventory/6')
        ->follow()
        ->assertScreen(InventoryItemDetail::class)
        ->assertNavTitle('Garage')
        ->assertSee('Place · 2 things inside')
        ->assertSee('What’s inside (2)')
        ->assertSee('2 things inside')
        ->tap('item-child-7')
        ->assertNavigatedTo('/inventory/7');
});

it('shows an item’s type, the trail to it, and its metadata', function () {
    Native::visit('/inventory/8')
        ->assertNavTitle('Cordless drill')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'item-summary'
            && trim($node['props']['text'] ?? '') === 'Thing')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-ancestor-6')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-parent')
        ->assertSee('You’re here')
        ->assertSee('Brand')
        ->assertSee('DeWalt')
        ->assertSee('Model')
        ->assertSee('DCD771')
        ->assertSee('Put something inside')
        ->assertAccessible();
});

it('jumps to any container in the path', function () {
    Native::visit('/inventory/8')
        ->tap('item-ancestor-6')
        ->assertNavigatedTo('/inventory/6');
});

it('adds a thing inside a place or storage', function () {
    Native::visit('/inventory/7')
        ->assertSee('Add something here')
        ->tap('Add one thing')
        ->assertNavigatedTo('/inventory/create')
        ->follow()
        ->assertSet('parentId', 7)
        ->assertSet('type', ItemType::Item);
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

it('shows a top-level place as one of the places in the home, without asking to put it away', function () {
    Native::visit('/inventory/1')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-parent')
        ->assertSee('One of the places in your home')
        ->assertDontSee('Not in a place yet')
        ->assertDontSee('Tap Move to put it away.')
        ->assertDontSee('Details');
});

it('asks to put away top-level storage and things', function () {
    Item::findOrFail(12)->update(['parent_id' => null]);

    Native::visit('/inventory/12')
        ->assertSee('Not in a place yet')
        ->assertSee('Tap Move to put it away.')
        ->assertDontSee('One of the places in your home');
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

it('finds everything nested inside an item with a single query', function () {
    DB::enableQueryLog();

    expect(Inventory::descendantIdsOf(6))->toEqualCanonicalizing([7, 8, 9, 10])
        ->and(Inventory::descendantIdsOf(8))->toBe([])
        ->and(collect(DB::getQueryLog())->filter(fn (array $query): bool => str_contains($query['query'], '"items"')))->toHaveCount(2);
});

it('stops walking descendants when the synced data contains a parent cycle', function () {
    Item::find(6)->update(['parent_id' => 8]);

    expect(Inventory::descendantIdsOf(6))->toEqualCanonicalizing([7, 8, 9, 10]);
});

it('puts labeled edit and print buttons above the tab bar and delete at the end of the page', function () {
    Native::visit('/inventory/7')
        ->assertElement('bottom_bar', fn (array $node): bool => array_column($node['children'][0]['children'], 'ref') === ['edit-item', 'item-print-label'])
        ->assertMissingElement('top_bar_action', fn (array $node): bool => ($node['ref'] ?? null) === 'delete-item')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'delete-item')
        ->assertSee('Delete Tool chest')
        ->tap('item-print-label')
        ->assertNavigatedTo('/inventory/7/qr-code');
});

it('lets a plain item hold other items, like insoles in shoes', function () {
    Native::visit('/inventory/8')
        ->assertElement('pressable', function (array $node): bool {
            return ($node['ref'] ?? null) === 'item-add'
                && array_column(array_column($node['children'], 'props'), 'label') === ['Add one thing', 'Photograph several things'];
        })
        ->tap('Add one thing')
        ->assertNavigatedTo('/inventory/create')
        ->follow()
        ->assertSet('parentId', 8);
});

it('invites adding to an empty container', function () {
    seedSunnyData();
    Item::create([...Item::find(7)->toArray(), 'id' => 90, 'name' => 'Empty bin']);

    Native::visit('/inventory/90')
        ->assertSee('What’s inside')
        ->assertSee('Put something inside')
        ->tap('Add one thing')
        ->assertNavigatedTo('/inventory/create');
});

it('keeps search alone at the bottom and puts labeled add and scan buttons above the list', function () {
    Native::visit('/inventory')
        ->assertElement('bottom_bar', fn (array $node): bool => array_values(array_filter(array_column($node['children'][0]['children'], 'ref'))) === [])
        ->assertElement('pressable', function (array $node): bool {
            return ($node['ref'] ?? null) === 'inventory-add'
                && array_column(array_column($node['children'], 'props'), 'label') === ['Add one thing', 'Photograph several things'];
        })
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'inventory-scan-label')
        ->assertAccessible();
});

it('refreshes the inventory after a sync without opening the scanner', function () {
    seedSunnyData();
    $screen = Native::visit('/inventory')->assertDontSee('Attic');

    Item::create([...Item::find(6)->toArray(), 'id' => 90, 'name' => 'Attic']);

    $screen->emitNative('sunny-sync-complete', ['status' => 'finished'])
        ->assertSee('Attic')
        ->assertNothingScanned();
});
