<?php

use App\Enums\ChecklistType;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\PendingWrite;
use App\Models\Team;
use App\NativeComponents\CreateList;
use App\NativeComponents\EditList;
use App\NativeComponents\ListDetail;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Victorycodedev\ToastKit\Events\ToastActionPressed;

beforeEach(fn () => seedSunnyData());

it('lists the team’s lists alphabetically with their progress', function () {
    $screen = Native::visit('/lists')
        ->assertNavTitle('Lists')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'checklist-1'
            && ($node['props']['a11y_label'] ?? null) === 'Groceries, Shopping · 1 of 3 done')
        ->assertSee('Wish list · No items')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'lists-count' && $node['props']['text'] === '3 lists')
        ->assertAccessible();

    expect(array_column($screen->get('lists'), 'name'))->toBe(['Birthday ideas', 'Chores', 'Groceries']);
});

it('shows no owners on the lists of a team with one member', function () {
    Team::find(1)->update(['members' => [['id' => 1, 'name' => 'Andy Swick', 'initials' => 'AS']]]);

    Native::visit('/lists')
        ->assertSee('Wish list · No items')
        ->assertDontSee('Andy’s')
        ->assertMissingElement('column', fn (array $node): bool => str_ends_with($node['ref'] ?? '', '-owner'));
});

it('shows who each list belongs to when the team has several members', function () {
    seedSunnyMembers();

    Native::visit('/lists')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'checklist-3-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Andy Swick')
        ->assertSee('AS')
        ->assertSee('Andy’s · Wish list · No items')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'checklist-1-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Household')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'checklist-1'
            && ($node['props']['a11y_label'] ?? null) === 'Groceries, Household, Shopping · 1 of 3 done')
        ->assertElement('text', fn (array $node): bool => ($node['ref'] ?? null) === 'lists-count' && $node['props']['text'] === '3 lists · 2 for the household')
        ->assertAccessible();
});

it('shows a list whose owner left the team as the household’s', function () {
    seedSunnyMembers();
    Checklist::find(3)->update(['user_id' => 99]);

    Native::visit('/lists')
        ->assertSee('Wish list · No items')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'checklist-3-owner'
            && ($node['props']['a11y_label'] ?? null) === 'Household');
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
    PendingWrite::sole()->update(['error' => 'This record or team is no longer on the server.']);

    Native::visit('/lists/1')->assertSee('Not saved. This record or team is no longer on the server.');
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

    expect($menu($tree, 'list-item-1-actions'))->toBe([['Edit', false], ['Open link', false], ['Delete', true]])
        ->and($menu($tree, 'list-item-3-actions'))->toBe([['Edit', false], ['Delete', true]]);
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
        ->tap('list-add-item')
        ->assertSet('newItem', 'Wool slippers')
        ->assertSee('That’s not a web address. Try one like lego.com');

    expect(PendingWrite::count())->toBe(0);
})->with([
    'words' => ['llbean slippers'],
    'no dot in the host' => ['llbean'],
    'ftp' => ['ftp://llbean.com/slippers'],
    'mailto' => ['mailto:help@llbean.com'],
    'javascript' => ['javascript:alert(1)'],
]);

it('adds https:// to a link typed without one', function (string $typed, string $saved) {
    Native::visit('/lists/3')
        ->set('newItem', 'Lego set')
        ->set('newItemUrl', $typed)
        ->assertSet('linkError', '')
        ->tap('list-add-item')
        ->assertSee('lego.com');

    expect(ChecklistItem::find(-1)->url)->toBe($saved)
        ->and(PendingWrite::sole()->payload['url'])->toBe($saved);
})->with([
    'a bare domain' => ['lego.com', 'https://lego.com'],
    'a domain with a path' => ['lego.com/product/123', 'https://lego.com/product/123'],
    'www' => ['www.lego.com', 'https://www.lego.com'],
    'http' => ['http://lego.com/product/123', 'http://lego.com/product/123'],
]);

it('uses the same add and more buttons on a wish list as on other lists', function () {
    $button = function (string $path, string $ref): array {
        $find = function (array $node) use (&$find, $ref): ?array {
            if (($node['ref'] ?? null) === $ref) {
                return $node;
            }

            foreach ($node['children'] ?? [] as $child) {
                if (($found = $find($child)) !== null) {
                    return $found;
                }
            }

            return null;
        };
        $node = $find(Native::visit($path)->tree());

        return [$node['layout'], $node['style'] ?? null, $node['props'], array_map(fn (array $child): array => [$child['type'], $child['props']], $node['children'])];
    };

    expect($button('/lists/3', 'list-add-item'))->toBe($button('/lists/1', 'list-add-item'))
        ->and($button('/lists/3', 'list-add-item')[1])->toBeNull()
        ->and(array_slice($button('/lists/3', 'list-more'), 0, 3))->toBe(array_slice($button('/lists/1', 'list-more'), 0, 3))
        ->and($button('/lists/3', 'list-more')[2]['glass'])->toBe(1);
});

it('waits for the rest of a web address before calling it wrong', function () {
    Native::visit('/lists/3')
        ->set('newItem', 'Wool slippers')
        ->set('newItemUrl', 'llbean slippers')
        ->assertSee('That’s not a web address. Try one like lego.com')
        ->set('newItemUrl', 'http')
        ->assertSet('linkError', '')
        ->set('newItemUrl', 'llbean')
        ->assertSet('linkError', '')
        ->tap('list-add-item')
        ->assertSee('That’s not a web address. Try one like lego.com')
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
        ->submit('list-edit-url', 'www.lego.com/product/orchid-10311')
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
        ->assertSee('That’s not a web address. Try one like lego.com')
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

it('closes the edit when its item is removed as done, and keeps it open otherwise', function () {
    Native::visit('/lists/1')
        ->press('editItem(2)')
        ->call('confirmRemoveDone')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'remove-done'])
        ->assertSet('editingItemId', null)
        ->assertDontSee('Edit item')
        ->assertDontSee('Eggs');

    ChecklistItem::query()->whereKey(1)->update(['completed_at' => now()]);

    Native::visit('/lists/1')
        ->press('editItem(3)')
        ->call('confirmRemoveDone')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'remove-done'])
        ->assertSet('editingItemId', 3)
        ->assertSee('Edit item')
        ->assertDontSee('Milk');
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

function listDetailRef(string $ref): Closure
{
    return fn (array $node): bool => ($node['ref'] ?? null) === $ref;
}

it('keeps the list’s actions in one menu, offering Delete done and Uncheck all only while something is ticked', function () {
    Native::visit('/lists/1')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'list-more'
            && collect($node['children'])->where('type', 'top_bar_action')->pluck('ref')->all() === ['edit-list', 'remove-done', 'uncheck-all', 'delete-list'])
        ->assertElement('top_bar_action', fn (array $node): bool => ($node['ref'] ?? null) === 'edit-list' && $node['props']['label'] === 'Edit' && $node['props']['a11y_label'] === 'Edit Groceries')
        ->assertElement('top_bar_action', fn (array $node): bool => ($node['ref'] ?? null) === 'remove-done' && $node['props']['label'] === 'Delete done')
        ->assertElement('top_bar_action', fn (array $node): bool => ($node['ref'] ?? null) === 'delete-list' && $node['props']['destructive'] === true)
        ->tap('list-item-2')
        ->assertMissingElement('top_bar_action', listDetailRef('remove-done'))
        ->assertMissingElement('top_bar_action', listDetailRef('uncheck-all'))
        ->assertElement('top_bar_action', listDetailRef('edit-list'));

    Native::visit('/lists/2')
        ->assertMissingElement('top_bar_action', listDetailRef('remove-done'))
        ->assertMissingElement('top_bar_action', listDetailRef('uncheck-all'));
});

it('asks before removing done items, naming them', function () {
    Native::visit('/lists/1')
        ->tap('list-item-1')
        ->tap('remove-done')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'remove-done'
            && $params['title'] === 'Delete 2 done items?'
            && $params['message'] === 'Milk and Eggs will be deleted from Groceries for everyone on your team.'
            && array_column($params['buttons'], 'label') === ['Cancel', 'Delete']);

    ChecklistItem::query()->where('checklist_id', 1)->update(['completed_at' => now()]);
    ChecklistItem::query()->create(['id' => 9, 'server' => ChecklistItem::find(1)->server, 'checklist_id' => 1, 'name' => 'Butter', 'position' => 4, 'completed_at' => now()]);
    ChecklistItem::query()->create(['id' => 10, 'server' => ChecklistItem::find(1)->server, 'checklist_id' => 1, 'name' => 'Jam', 'position' => 5, 'completed_at' => now()]);

    Native::visit('/lists/1')
        ->tap('remove-done')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['message'] === 'Milk, Eggs, Bread and 2 more will be deleted from Groceries for everyone on your team.');

    ChecklistItem::query()->where('checklist_id', 1)->where('id', '!=', 2)->delete();

    Native::visit('/lists/1')
        ->tap('remove-done')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['title'] === 'Delete 1 done item?'
            && $params['message'] === 'Eggs will be deleted from Groceries for everyone on your team.');
});

it('keeps done items when the removal is cancelled', function () {
    Native::visit('/lists/1')
        ->tap('remove-done')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'remove-done'])
        ->assertSee('Eggs');

    expect(ChecklistItem::find(2))->not->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('removes done items on the phone straight away and queues one clear for Sunny', function () {
    $screen = Native::visit('/lists/1')
        ->tap('list-item-1')
        ->tap('remove-done')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'remove-done'])
        ->assertDontSee('Milk')
        ->assertDontSee('Eggs')
        ->assertSee('Shopping · 0 of 1 done')
        ->assertMissingElement('top_bar_action', listDetailRef('remove-done'));

    expect(array_column($screen->get('items'), 'name'))->toBe(['Bread'])
        ->and(PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'deletes')->all())->toBe([
            ['resource' => 'checklist_items', 'record_id' => 1, 'deletes' => true],
            ['resource' => 'checklist_clears', 'record_id' => 1, 'deletes' => false],
        ]);
});

it('unchecks every item without asking, and offers to undo it in a toast', function () {
    $screen = Native::visit('/lists/1')
        ->tap('list-item-1')
        ->tap('uncheck-all')
        ->assertNativeNotCalled('Dialog.Alert')
        ->assertSee('Shopping · 0 of 3 done')
        ->assertToastShown(fn (array $params): bool => $params['message'] === '2 items unchecked' && $params['variant'] === 'success'
            && $params['action'] === ['id' => 'undo-uncheck', 'label' => 'Undo'] && $params['data'] === ['list' => 1, 'items' => [1, 2]])
        ->assertMissingElement('top_bar_action', listDetailRef('uncheck-all'))
        ->assertDontSee('items unchecked')
        ->assertMissingElement('row', listDetailRef('list-unchecked'))
        ->assertMissingElement('pressable', listDetailRef('list-undo-uncheck'))
        ->assertAccessible();

    expect(array_column($screen->get('items'), 'completed'))->toBe([false, false, false])
        ->and(PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'payload')->all())->toBe([
            ['resource' => 'checklist_items', 'record_id' => 1, 'payload' => ['completed' => false, 'checklist_id' => 1]],
            ['resource' => 'checklist_resets', 'record_id' => 1, 'payload' => []],
        ]);

    $screen->emitNative(ToastActionPressed::class, ['toastId' => 'unchecked', 'actionId' => 'undo-uncheck', 'data' => ['list' => 1, 'items' => [1, 2]]])
        ->assertSee('Shopping · 2 of 3 done');

    expect(array_column($screen->get('items'), 'completed'))->toBe([true, true, false])
        ->and(PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'payload')->all())->toBe([
            ['resource' => 'checklist_items', 'record_id' => 1, 'payload' => ['completed' => true, 'checklist_id' => 1]],
            ['resource' => 'checklist_items', 'record_id' => 2, 'payload' => ['completed' => true, 'checklist_id' => 1]],
        ]);
});

it('names a single unchecked item in the toast', function () {
    Native::visit('/lists/1')
        ->tap('uncheck-all')
        ->assertToastShown(fn (array $params): bool => $params['message'] === '1 item unchecked' && $params['data'] === ['list' => 1, 'items' => [2]]);
});

it('re-ticks the items with a queued change once the reset has gone out', function () {
    $screen = Native::visit('/lists/1')->tap('uncheck-all');

    PendingWrite::query()->where('resource', 'checklist_resets')->delete();

    $screen->emitNative(ToastActionPressed::class, ['toastId' => 'unchecked', 'actionId' => 'undo-uncheck', 'data' => ['list' => 1, 'items' => [2]]])
        ->assertSee('Shopping · 1 of 3 done');

    expect(PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'payload')->all())->toBe([
        ['resource' => 'checklist_items', 'record_id' => 2, 'payload' => ['completed' => true, 'checklist_id' => 1]],
    ]);
});

it('ignores an undo meant for another list', function () {
    $screen = Native::visit('/lists/1')->tap('uncheck-all');

    $screen->emitNative(ToastActionPressed::class, ['toastId' => 'unchecked', 'actionId' => 'undo-uncheck', 'data' => ['list' => 2, 'items' => [2]]])
        ->assertSee('Shopping · 0 of 3 done');

    expect(ChecklistItem::find(2)->completed_at)->toBeNull()
        ->and(PendingWrite::orderBy('id')->pluck('resource')->all())->toBe(['checklist_resets']);
});

it('skips items removed or ticked again since unchecking', function () {
    ChecklistItem::query()->whereKey(1)->update(['completed_at' => now()]);

    $screen = Native::visit('/lists/1')
        ->tap('uncheck-all')
        ->press('removeItem(1)')
        ->tap('list-item-2');

    $before = PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'payload', 'deletes', 'version')->all();

    $screen->emitNative(ToastActionPressed::class, ['toastId' => 'unchecked', 'actionId' => 'undo-uncheck', 'data' => ['list' => 1, 'items' => [1, 2]]])
        ->assertSee('Shopping · 1 of 2 done');

    expect(PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'payload', 'deletes', 'version')->all())->toBe($before)
        ->and(PendingWrite::query()->where('resource', 'checklist_resets')->exists())->toBeTrue();
});

it('keeps the items in place while the menu comes and goes', function () {
    $ids = [];
    $items = function (array $node) use (&$ids): bool {
        if (($node['ref'] ?? null) !== 'list-detail') {
            return false;
        }

        $ids[] = $node['id'];

        return true;
    };

    Native::visit('/lists/1')
        ->assertElement('scroll_view', $items)
        ->tap('uncheck-all')
        ->assertElement('scroll_view', $items)
        ->tap('list-item-3')
        ->assertElement('scroll_view', $items)
        ->tap('list-item-3')
        ->assertElement('scroll_view', $items);

    expect($ids)->toHaveCount(4)->and(array_unique($ids))->toHaveCount(1);
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
    Native::visit('/lists/-1')->assertSee('This list is empty.')->assertDontSee('syncing with Sunny');
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

it('leaves out the owner picker for a team with one member and gives them new lists', function () {
    Team::find(1)->update(['members' => [['id' => 1, 'name' => 'Andy Swick', 'initials' => 'AS']]]);

    Native::visit('/lists/create')
        ->assertDontSee('Belongs to')
        ->assertSet('ownerOptions', [])
        ->set('name', 'Hardware store')
        ->tap('create-list-submit')
        ->assertReplacedWith('/lists/-1');

    expect(Checklist::find(-1)->user_id)->toBe(1)
        ->and(PendingWrite::sole()->payload)->toBe(['name' => 'Hardware store', 'type' => 'todo', 'user_id' => 1]);
});

it('keeps a list’s owner when a team with one member edits it', function () {
    Team::find(1)->update(['members' => [['id' => 1, 'name' => 'Andy Swick', 'initials' => 'AS']]]);

    Native::visit('/lists/1/edit')->assertDontSee('Belongs to')->set('name', 'Pantry')->tap('edit-list-submit');

    expect(Checklist::find(1)->user_id)->toBeNull()
        ->and(PendingWrite::sole()->payload)->not->toHaveKey('user_id');
});

it('offers the household and each member as owners of a new list', function () {
    seedSunnyMembers();

    Native::visit('/lists/create')
        ->assertSee('Belongs to')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-list-owner-0'
            && ($node['props']['a11y_label'] ?? null) === 'Household, selected')
        ->assertSee('Everyone in the team')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-list-owner-1'
            && ($node['props']['a11y_label'] ?? null) === 'Andy Swick')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-list-owner-7'
            && ($node['props']['a11y_label'] ?? null) === 'Sam Swick')
        ->assertAccessible();
});

it('creates a list for the household or for one member', function (?string $tap, ?int $userId) {
    seedSunnyMembers();

    $screen = Native::visit('/lists/create')->set('name', 'Hardware store');
    if ($tap !== null) {
        $screen->tap($tap)->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === $tap
            && str_ends_with($node['props']['a11y_label'] ?? '', ', selected'));
    }
    $screen->tap('create-list-submit')->assertReplacedWith('/lists/-1');

    expect(Checklist::find(-1)->user_id)->toBe($userId)
        ->and(PendingWrite::sole()->payload)->toBe(['name' => 'Hardware store', 'type' => 'todo', 'user_id' => $userId]);
})->with([
    'household' => [null, null],
    'a member' => ['create-list-owner-7', 7],
    'back to the household' => ['create-list-owner-0', null],
]);

it('changes who a list belongs to', function (int $listId, string $tap, ?int $userId) {
    seedSunnyMembers();

    Native::visit("/lists/{$listId}/edit")
        ->tap($tap)
        ->tap('edit-list-submit')
        ->assertWentBack();

    expect(Checklist::find($listId)->user_id)->toBe($userId)
        ->and(PendingWrite::sole()->payload)->toMatchArray(['user_id' => $userId]);
})->with([
    'household to a member' => [1, 'edit-list-owner-7', 7],
    'member to the household' => [3, 'edit-list-owner-0', null],
    'member to another member' => [3, 'edit-list-owner-7', 7],
]);

it('fills in a list’s owner when editing', function () {
    seedSunnyMembers();

    Native::visit('/lists/3/edit')
        ->assertSet('userId', 1)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'edit-list-owner-1'
            && ($node['props']['a11y_label'] ?? null) === 'Andy Swick, selected');
});

it('gives a list whose owner left the team back to the household', function () {
    seedSunnyMembers();
    Checklist::find(3)->update(['user_id' => 99]);

    Native::visit('/lists/3/edit')
        ->assertSet('userId', null)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'edit-list-owner-0'
            && ($node['props']['a11y_label'] ?? null) === 'Household, selected')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'edit-list-owner-99')
        ->set('name', 'Gift ideas')
        ->tap('edit-list-submit');

    expect(PendingWrite::sole()->payload)->toBe(['name' => 'Gift ideas', 'type' => 'wishlist', 'user_id' => null]);
});
