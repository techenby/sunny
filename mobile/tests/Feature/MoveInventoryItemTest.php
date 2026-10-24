<?php

use App\Models\Item;
use App\Models\PendingWrite;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('moves an item to another place from its page', function () {
    Native::visit('/inventory/10')
        ->tap('item-move')
        ->assertSet('showParentPicker', true)
        ->assertSet('parentBrowseId', null)
        ->tap('move-parent-11')
        ->assertSee('Put it in Basement')
        ->tap('move-parent-choose')
        ->assertSet('showParentPicker', false)
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === 'Moved to Basement')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-parent');

    expect(Item::find(10)->parent_id)->toBe(11)
        ->and(PendingWrite::sole()->payload)->toMatchArray(['name' => 'Camping tent', 'type' => 'item', 'parent_id' => 11]);
});

it('takes an item out of every place', function () {
    Native::visit('/inventory/10')
        ->tap('item-move')
        ->tap('move-parent-choose')
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === 'Moved to “Not in a place yet”')
        ->assertSee('Not in a place yet');

    expect(Item::find(10)->parent_id)->toBeNull();
});

it('moves a place back to the places in the home', function () {
    Item::findOrFail(1)->update(['parent_id' => 6]);

    Native::visit('/inventory/1')
        ->tap('item-move')
        ->assertSee('Put it with the places in your home')
        ->tap('move-parent-choose')
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === 'Now one of the places in your home')
        ->assertSee('One of the places in your home');

    expect(Item::find(1)->parent_id)->toBeNull();
});

it('leaves an item alone when it is put back where it was', function () {
    Native::visit('/inventory/10')
        ->tap('item-move')
        ->tap('move-parent-6')
        ->tap('move-parent-choose');

    expect(Item::find(10)->parent_id)->toBe(6)
        ->and(PendingWrite::count())->toBe(0);
});

it('does not offer the item or anything inside it as a new place', function () {
    expect(array_column(Native::visit('/inventory/6')->tap('item-move')->get('parentChoices'), 'name'))->toBe([
        'Basement', 'Holiday decorations', 'Ornaments', 'String lights', 'Spare light bulbs',
        'Kitchen', 'Pantry', 'Basmati rice', 'Canned tomatoes', 'Olive oil',
    ]);
});
