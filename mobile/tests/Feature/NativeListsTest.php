<?php

use App\Enums\ChecklistType;
use App\Http\Integrations\Sunny\SunnyStore;
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
            && ($node['props']['supporting'] ?? null) === 'Wish list · No items')
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

it('opens an item’s link in the in-app browser', function () {
    $bridge = Native::fakeBridge()->respondTo('Browser.OpenInApp', ['success' => true]);

    Native::visit('/lists/1')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'list-item-3-link')
        ->tap('list-item-1-link');

    $bridge->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://www.example.com/oat-milk');
    expect(ChecklistItem::find(1)->completed_at)->toBeNull();
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

it('renames an item from the keyboard without changing whether it is checked', function () {
    $screen = Native::visit('/lists/1')
        ->press('editItem(2)')
        ->assertSet('editingName', 'Eggs')
        ->assertElement('bare_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'list-edit-name')
        ->assertAccessible()
        ->submit('list-edit-name', '  Free-range eggs ')
        ->assertSet('editingItemId', null)
        ->assertSee('Free-range eggs')
        ->assertSee('Shopping · 1 of 3 done');

    expect(array_column($screen->get('items'), 'name'))->toBe(['Milk', 'Free-range eggs', 'Bread'])
        ->and(ChecklistItem::find(2)->completed_at)->not->toBeNull()
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'payload'))->toBe([
            'resource' => 'checklist_items', 'record_id' => 2, 'payload' => ['name' => 'Free-range eggs', 'checklist_id' => 1],
        ]);
});

it('saves the typed name with the save button', function () {
    Native::visit('/lists/1')
        ->press('editItem(1)')
        ->set('editingName', 'Oat milk')
        ->tap('list-edit-save')
        ->assertSee('Oat milk');

    expect(ChecklistItem::find(1)->name)->toBe('Oat milk');
});

it('renames an item that has not reached Sunny yet as part of creating it', function () {
    Native::visit('/lists/1')
        ->submit('list-new-item', 'Cofee')
        ->press('editItem(-1)')
        ->submit('list-edit-name', 'Coffee');

    expect(PendingWrite::sole()->only('record_id', 'payload'))->toBe(['record_id' => -1, 'payload' => ['checklist_id' => 1, 'name' => 'Coffee']])
        ->and(PendingWrite::sole()->client_uuid)->not->toBeNull();
});

it('cancels an edit or an unchanged name without queueing anything', function () {
    Native::visit('/lists/1')
        ->press('editItem(1)')
        ->set('editingName', 'Oat milk')
        ->tap('list-edit-cancel')
        ->assertSet('editingItemId', null)
        ->assertSee('Milk')
        ->press('editItem(1)')
        ->submit('list-edit-name', 'Milk')
        ->assertSet('editingItemId', null);

    expect(ChecklistItem::find(1)->name)->toBe('Milk')->and(PendingWrite::count())->toBe(0);
});

it('keeps editing when the new name is blank or too long', function (string $name, string $message) {
    Native::visit('/lists/1')
        ->press('editItem(1)')
        ->submit('list-edit-name', $name)
        ->assertSee($message)
        ->assertSet('editingItemId', 1);

    expect(ChecklistItem::find(1)->name)->toBe('Milk')->and(PendingWrite::count())->toBe(0);
})->with([
    'blank' => ['   ', 'Give the item a name.'],
    'too long' => [str_repeat('a', 256), 'The item is too long (255 characters max).'],
]);

it('removes an item and queues its deletion', function () {
    $screen = Native::visit('/lists/1')->press('removeItem(3)')->assertDontSee('Bread');

    expect(ChecklistItem::find(3))->toBeNull()
        ->and(PendingWrite::sole()->only('record_id', 'deletes', 'payload'))->toBe(['record_id' => 3, 'deletes' => true, 'payload' => ['checklist_id' => 1]])
        ->and(array_column($screen->get('items'), 'name'))->toBe(['Milk', 'Eggs']);
});

it('removes an item that never reached Sunny without queueing anything', function () {
    Native::visit('/lists/1')->submit('list-new-item', 'Coffee')->press('removeItem(-1)')->assertDontSee('Coffee');

    expect(ChecklistItem::find(-1))->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('flags an item change Sunny refused', function () {
    Native::visit('/lists/1')->tap('list-item-1');
    PendingWrite::sole()->update(['error' => 'This record or team is no longer on Sunny.']);

    Native::visit('/lists/1')->assertSee('Not saved to Sunny. This record or team is no longer on Sunny.');
});

it('shows a link’s domain under the item’s name and opens it from there', function () {
    $bridge = Native::fakeBridge()->respondTo('Browser.OpenInApp', ['success' => true]);

    $screen = Native::visit('/lists/1')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'list-item-1-domain' && ($node['props']['text'] ?? null) === 'example.com')
        ->assertMissingElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'list-item-3-domain')
        ->tap('list-item-1-link');

    $bridge->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://www.example.com/oat-milk');
    expect(array_column($screen->get('items'), 'domain'))->toBe(['example.com', null, null]);
});

it('puts edit, open link and remove in each item’s menu', function () {
    $menu = function (array $node, string $ref) use (&$menu): ?array {
        if (($node['ref'] ?? null) === $ref) {
            return collect($node['children'])->where('type', 'top_bar_action')->map(fn (array $action): array => [$action['props']['label'], $action['props']['destructive'] ?? false])->values()->all();
        }

        foreach ($node['children'] ?? [] as $child) {
            if (($found = $menu($child, $ref)) !== null) {
                return $found;
            }
        }

        return null;
    };

    $tree = Native::visit('/lists/1')->assertAccessible()->tree();

    expect($menu($tree, 'list-item-1-actions'))->toBe([['Edit', false], ['Open link', false], ['Remove', true]])
        ->and($menu($tree, 'list-item-3-actions'))->toBe([['Edit', false], ['Remove', true]]);
});

it('opens an item’s link from its menu', function () {
    $bridge = Native::fakeBridge()->respondTo('Browser.OpenInApp', ['success' => true]);

    Native::visit('/lists/1')->press('openItemLink(1)');

    $bridge->assertCalled('Browser.OpenInApp', fn (array $params): bool => $params['url'] === 'https://www.example.com/oat-milk');
});

it('offers a link when adding only on a wish list', function () {
    Native::visit('/lists/3')
        ->assertElement('bare_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'list-new-item-url')
        ->assertSee('Link is optional. Type it, or paste one in.')
        ->assertAccessible();

    Native::visit('/lists/1')->assertMissingElement('bare_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'list-new-item-url');
});

it('adds an item with a link to a wish list', function () {
    $screen = Native::visit('/lists/3')
        ->set('newItem', 'Wool slippers')
        ->submit('list-new-item-url', ' https://www.llbean.com/wicked-good-slippers ')
        ->assertSet('newItem', '')
        ->assertSet('newItemUrl', '')
        ->assertSee('Wool slippers')
        ->assertSee('llbean.com');

    expect($screen->get('items')[0])->toMatchArray(['name' => 'Wool slippers', 'url' => 'https://www.llbean.com/wicked-good-slippers'])
        ->and(ChecklistItem::find(-1)->url)->toBe('https://www.llbean.com/wicked-good-slippers')
        ->and(PendingWrite::sole()->payload)->toBe(['checklist_id' => 3, 'name' => 'Wool slippers', 'url' => 'https://www.llbean.com/wicked-good-slippers']);
});

it('adds an item without a link to a wish list', function () {
    Native::visit('/lists/3')
        ->set('newItem', 'Concert tickets')
        ->tap('list-add-item')
        ->assertSet('newItem', '')
        ->assertSee('Concert tickets');

    expect(ChecklistItem::find(-1)->url)->toBeNull()
        ->and(PendingWrite::sole()->payload)->toBe(['checklist_id' => 3, 'name' => 'Concert tickets']);
});

it('refuses a link that isn’t a web address', function (string $url) {
    Native::visit('/lists/3')
        ->set('newItem', 'Wool slippers')
        ->set('newItemUrl', $url)
        ->assertSee('That’s not a web address. Links start with https://')
        ->tap('list-add-item')
        ->assertSet('newItem', 'Wool slippers')
        ->assertSee('That’s not a web address. Links start with https://');

    expect(PendingWrite::count())->toBe(0);
})->with([
    'words' => ['llbean slippers'],
    'a bare domain' => ['llbean.com/slippers'],
    'another scheme' => ['javascript:alert(1)'],
]);

it('waits for the rest of a web address before calling it wrong', function () {
    Native::visit('/lists/3')
        ->set('newItem', 'Wool slippers')
        ->set('newItemUrl', 'http')
        ->assertSet('linkError', '')
        ->tap('list-add-item')
        ->assertSee('That’s not a web address. Links start with https://')
        ->set('newItemUrl', 'https://www.llbean.com')
        ->assertSet('linkError', '');

    expect(PendingWrite::count())->toBe(0);
});

it('clears a typed link', function () {
    Native::visit('/lists/3')
        ->set('newItemUrl', 'llbean slippers')
        ->tap('list-new-item-url-clear')
        ->assertSet('newItemUrl', '')
        ->assertSet('linkError', '')
        ->assertSee('Link is optional. Type it, or paste one in.');
});

it('edits an item’s name and link', function () {
    app(SunnyStore::class)->saveRecord('checklist_items', ['id' => 5, 'checklist_id' => 3, 'name' => 'Lego set', 'url' => 'https://www.lego.com', 'position' => 1]);

    $screen = Native::visit('/lists/3')
        ->press('editItem(5)')
        ->assertSet('editingUrl', 'https://www.lego.com')
        ->assertSee('Edit item')
        ->assertAccessible()
        ->set('editingName', 'Lego Botanical set')
        ->submit('list-edit-url', 'https://www.lego.com/product/orchid-10311')
        ->assertSet('editingItemId', null)
        ->assertSee('Lego Botanical set');

    expect($screen->get('items')[0])->toMatchArray(['url' => 'https://www.lego.com/product/orchid-10311', 'domain' => 'lego.com'])
        ->and(PendingWrite::sole()->only('record_id', 'payload'))->toBe([
            'record_id' => 5, 'payload' => ['name' => 'Lego Botanical set', 'url' => 'https://www.lego.com/product/orchid-10311', 'checklist_id' => 3],
        ]);
});

it('adds a link to an item on a wish list but not on other lists', function () {
    app(SunnyStore::class)->saveRecord('checklist_items', ['id' => 5, 'checklist_id' => 3, 'name' => 'Field Notes', 'position' => 1]);

    Native::visit('/lists/3')
        ->press('editItem(5)')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'list-edit-remove-link')
        ->set('editingUrl', 'https://fieldnotesbrand.com')
        ->tap('list-edit-save');

    expect(ChecklistItem::find(5)->url)->toBe('https://fieldnotesbrand.com')
        ->and(PendingWrite::sole()->payload)->toBe(['url' => 'https://fieldnotesbrand.com', 'checklist_id' => 3]);

    Native::visit('/lists/1')
        ->press('editItem(3)')
        ->assertMissingElement('bare_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'list-edit-url');
});

it('keeps editing when the new link isn’t a web address', function () {
    Native::visit('/lists/1')
        ->press('editItem(1)')
        ->set('editingUrl', 'oat milk')
        ->assertSee('That’s not a web address. Links start with https://')
        ->tap('list-edit-save')
        ->assertSet('editingItemId', 1);

    expect(ChecklistItem::find(1)->url)->toBe('https://www.example.com/oat-milk')->and(PendingWrite::count())->toBe(0);
});

it('removes an item’s link', function () {
    $screen = Native::visit('/lists/1')
        ->press('editItem(1)')
        ->tap('list-edit-remove-link')
        ->assertSet('editingItemId', null)
        ->assertDontSee('example.com');

    expect($screen->get('items')[0])->toMatchArray(['name' => 'Milk', 'url' => null, 'domain' => null])
        ->and(PendingWrite::sole()->payload)->toBe(['url' => null, 'checklist_id' => 1]);
});

it('removes the item being edited and closes the edit', function () {
    Native::visit('/lists/1')
        ->press('editItem(1)')
        ->press('removeItem(1)')
        ->assertSet('editingItemId', null)
        ->assertDontSee('Edit item')
        ->assertDontSee('Milk');

    expect(PendingWrite::sole()->only('record_id', 'deletes'))->toBe(['record_id' => 1, 'deletes' => true]);
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
    Native::visit('/lists/99')->assertSee('List not found');
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
        ->assertSet('typeOptions', ['To-do', 'Shopping', 'Wish list'])
        ->assertAccessible();
});

it('creates a list on the phone and opens it', function () {
    Native::visit('/lists/create')
        ->input('create-list-name', ' Hardware store ')
        ->tap('create-list-type-1')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-list-type-1'
            && ($node['props']['a11y_label'] ?? null) === 'Shopping, selected')
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
        ->assertWentBack();

    expect(Checklist::find(1)->only('name', 'type', 'user_id'))->toBe(['name' => 'Pantry', 'type' => ChecklistType::Todo, 'user_id' => null])
        ->and(PendingWrite::sole()->payload)->toBe(['name' => 'Pantry', 'type' => 'todo']);
});

it('keeps a list’s owner when it is edited', function () {
    Native::visit('/lists/3/edit')->set('name', 'Gift ideas')->tap('edit-list-submit');

    expect(Checklist::find(3)->user_id)->toBe(1)
        ->and(PendingWrite::sole()->payload)->not->toHaveKey('user_id');
});
