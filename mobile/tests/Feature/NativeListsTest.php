<?php

use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\PendingWrite;
use App\NativeComponents\CreateList;
use App\NativeComponents\EditList;
use App\NativeComponents\ListDetail;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('lists the team’s lists alphabetically with their progress', function () {
    $screen = Native::visit('/lists')
        ->assertNavTitle('Lists')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Groceries'
            && ($node['props']['supporting'] ?? null) === 'Shopping · 1 of 3 done')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Birthday ideas'
            && ($node['props']['supporting'] ?? null) === 'Wish List · No items')
        ->assertAccessible();

    $headlines = [];
    $collect = function (array $node) use (&$collect, &$headlines): void {
        if (($node['type'] ?? null) === 'list_item') {
            $headlines[] = $node['props']['headline'];
        }

        foreach ($node['children'] ?? [] as $child) {
            $collect($child);
        }
    };
    $collect($screen->tree());

    expect($headlines)->toBe(['Birthday ideas', 'Chores', 'Groceries']);
});

it('explains how to start when the team has no lists', function () {
    Checklist::query()->delete();

    Native::visit('/lists')->assertSee('No lists yet');
});

it('filters lists by name as you search', function () {
    Native::visit('/lists')
        ->input('updateSearch', ' GROC ')
        ->assertSee('Groceries')
        ->assertDontSee('Chores')
        ->input('updateSearch', 'camping')
        ->assertSee('No lists match “camping”')
        ->input('updateSearch', '')
        ->assertSee('Chores');
});

it('opens a list from the overview', function () {
    Native::visit('/lists')
        ->tap('Groceries')
        ->assertNavigatedTo('/lists/1')
        ->follow()
        ->assertScreen(ListDetail::class)
        ->assertNavTitle('Groceries');
});

it('shows a list’s items in order with their progress', function () {
    $screen = Native::visit('/lists/1')
        ->assertSee('Shopping · 1 of 3 done')
        ->assertAccessible();

    expect(array_column($screen->get('items'), 'name'))->toBe(['Milk', 'Eggs', 'Bread'])
        ->and(array_column($screen->get('items'), 'completed'))->toBe([false, true, false]);
});

it('shows an empty list', function () {
    Native::visit('/lists/3')->assertSee('This list is empty.');
});

it('checks and unchecks an item on the phone and queues it for Sunny', function () {
    $screen = Native::visit('/lists/1')
        ->tap('list-item-1')
        ->assertSee('Shopping · 2 of 3 done');

    expect(ChecklistItem::find(1)->completed_at)->not->toBeNull()
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'payload'))->toBe([
            'resource' => 'checklist_items', 'record_id' => 1, 'payload' => ['completed' => true, 'checklist_id' => 1],
        ]);

    $screen->tap('list-item-1')->assertSee('Shopping · 1 of 3 done');

    expect(ChecklistItem::find(1)->completed_at)->toBeNull()
        ->and(PendingWrite::sole()->payload['completed'])->toBeFalse();
});

it('unchecks an item someone else checked', function () {
    Native::visit('/lists/1')->tap('list-item-2');

    expect(ChecklistItem::find(2)->only('completed_at', 'completed_by'))->toBe(['completed_at' => null, 'completed_by' => null]);
});

it('adds an item to the end of the list from the keyboard', function () {
    $screen = Native::visit('/lists/1')
        ->submit('list-new-item', '  Coffee ')
        ->assertSee('Coffee')
        ->assertSet('newItem', '');

    expect(array_column($screen->get('items'), 'name'))->toBe(['Milk', 'Eggs', 'Bread', 'Coffee'])
        ->and(ChecklistItem::find(-1)->only('checklist_id', 'name', 'position'))->toBe(['checklist_id' => 1, 'name' => 'Coffee', 'position' => 4])
        ->and(PendingWrite::sole()->client_uuid)->not->toBeNull();
});

it('adds the typed item with the add button', function () {
    Native::visit('/lists/3')
        ->set('newItem', 'Headphones')
        ->tap('list-add-item')
        ->assertSee('Headphones')
        ->assertSet('newItem', '');
});

it('ignores a blank item and rejects one that is too long', function () {
    Native::visit('/lists/1')
        ->submit('list-new-item', '   ')
        ->submit('list-new-item', str_repeat('a', 256))
        ->assertSee('The item is too long (255 characters max).');

    expect(PendingWrite::count())->toBe(0);
});

it('removes an item and queues its deletion', function () {
    $screen = Native::visit('/lists/1')->tap('list-item-3-remove')->assertDontSee('Bread');

    expect(ChecklistItem::find(3))->toBeNull()
        ->and(PendingWrite::sole()->only('record_id', 'deletes', 'payload'))->toBe(['record_id' => 3, 'deletes' => true, 'payload' => ['checklist_id' => 1]])
        ->and(array_column($screen->get('items'), 'name'))->toBe(['Milk', 'Eggs']);
});

it('removes an item that never reached Sunny without queueing anything', function () {
    Native::visit('/lists/1')->submit('list-new-item', 'Coffee')->tap('list-item--1-remove')->assertDontSee('Coffee');

    expect(ChecklistItem::find(-1))->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('flags an item change Sunny refused', function () {
    Native::visit('/lists/1')->tap('list-item-1');
    PendingWrite::sole()->update(['error' => 'This record or team is no longer on Sunny.']);

    Native::visit('/lists/1')->assertSee('Not saved to Sunny. This record or team is no longer on Sunny.');
});

it('asks before deleting a list', function () {
    Native::visit('/lists/1')
        ->tap('delete-list')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'delete-list')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-list'])
        ->assertNoNavigation();

    expect(Checklist::find(1))->not->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('deletes a list and its items once confirmed', function () {
    Native::visit('/lists/1')
        ->tap('list-item-1')
        ->tap('delete-list')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-list'])
        ->assertWentBack();

    expect(Checklist::find(1))->toBeNull()
        ->and(ChecklistItem::where('checklist_id', 1)->count())->toBe(0)
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'deletes'))->toBe(['resource' => 'checklists', 'record_id' => 1, 'deletes' => true]);
    Native::visit('/lists')->assertDontSee('Groceries');
});

it('shows a missing list', function () {
    Native::visit('/lists/99')->assertSee('This list could not be found.');
});

it('opens the create screen from the overview', function () {
    Native::visit('/lists')
        ->tap('New list')
        ->assertNavigatedTo('/lists/create')
        ->follow()
        ->assertScreen(CreateList::class)
        ->assertNavTitle('New list')
        ->assertSee('Name')
        ->assertSee('Type')
        ->assertSet('typeOptions', ['To-do', 'Shopping', 'Wish List'])
        ->assertAccessible();
});

it('creates a list on the phone and opens it', function () {
    Native::visit('/lists/create')
        ->input('create-list-name', ' Hardware store ')
        ->set('typeIndex', 1)
        ->tap('create-list-submit')
        ->assertSet('error', '')
        ->assertReplacedWith('/lists/-1');

    expect(Checklist::find(-1)->only('team_id', 'name', 'type'))->toBe(['team_id' => 1, 'name' => 'Hardware store', 'type' => ChecklistType::Shopping])
        ->and(PendingWrite::sole()->payload)->toBe(['name' => 'Hardware store', 'type' => 'shopping']);
    Native::visit('/lists/-1')->assertSee('This list is empty.')->assertSee('Saved on this phone · syncing with Sunny');
});

it('refuses to save a list without a valid name', function (string $name, string $message) {
    Native::visit('/lists/create')
        ->set('name', $name)
        ->tap('create-list-submit')
        ->assertNoNavigation()
        ->assertSee($message);
})->with([
    'blank' => ['   ', 'Give the list a name.'],
    'too long' => [str_repeat('a', 256), 'The name is too long (255 characters max).'],
]);

it('edits a list’s name and type', function () {
    Native::visit('/lists/1')
        ->tap('edit-list')
        ->assertNavigatedTo('/lists/1/edit')
        ->follow()
        ->assertScreen(EditList::class)
        ->assertSet('name', 'Groceries')
        ->assertSet('typeIndex', 1)
        ->set('name', 'Pantry')
        ->set('typeIndex', 0)
        ->tap('edit-list-submit')
        ->assertReplacedWith('/lists/1');

    expect(Checklist::find(1)->only('name', 'type', 'user_id'))->toBe(['name' => 'Pantry', 'type' => ChecklistType::Todo, 'user_id' => null])
        ->and(PendingWrite::sole()->payload)->toBe(['name' => 'Pantry', 'type' => 'todo']);
});

it('keeps a list’s owner when it is edited', function () {
    Native::visit('/lists/3/edit')->set('name', 'Gift ideas')->tap('edit-list-submit');

    expect(Checklist::find(3)->user_id)->toBe(1)
        ->and(PendingWrite::sole()->payload)->not->toHaveKey('user_id');
});
