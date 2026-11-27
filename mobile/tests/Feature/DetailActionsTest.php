<?php

use App\Models\Item;
use App\Models\Recipe;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

function detailNode(array $node, callable $matches): ?array
{
    if ($matches($node)) {
        return $node;
    }

    foreach ($node['children'] ?? [] as $child) {
        if (($found = detailNode($child, $matches)) !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * @return list<string>
 */
function detailBarButtons(array $tree): array
{
    $refs = [];
    $collect = function (array $node) use (&$collect, &$refs): void {
        if ($node['type'] === 'pressable') {
            $refs[] = $node['ref'] ?? null;
        }

        foreach ($node['children'] ?? [] as $child) {
            $collect($child);
        }
    };

    $collect(detailNode($tree, fn (array $node): bool => $node['type'] === 'bottom_bar'));

    return $refs;
}

/**
 * @return list<array<string, mixed>>
 */
function detailMenu(array $tree, string $more): array
{
    $button = detailNode($tree, fn (array $node): bool => ($node['ref'] ?? null) === $more);

    return array_values(array_filter($button['children'], fn (array $child): bool => $child['type'] === 'top_bar_action'));
}

/**
 * @return list<string>
 */
function detailTopBarActions(array $tree): array
{
    $refs = [];
    $collect = function (array $node, bool $inMenu) use (&$collect, &$refs): void {
        if ($node['type'] === 'top_bar_action' && ! $inMenu) {
            $refs[] = $node['props']['id'];
        }

        foreach ($node['children'] ?? [] as $child) {
            $collect($child, $inMenu || $node['type'] === 'pressable');
        }
    };

    $collect($tree, false);

    return $refs;
}

it('ends a detail screen’s bottom bar with a glass more circle', function (string $uri, array $buttons) {
    $tree = Native::visit($uri, platform: 'ios')->assertAccessible()->tree();
    $more = detailNode($tree, fn (array $node): bool => ($node['ref'] ?? null) === end($buttons));

    expect(detailBarButtons($tree))->toBe($buttons)
        ->and($more['props']['glass'])->toBe(1)
        ->and($more['layout']['width'])->toEqual(48)
        ->and($more['props']['a11y_label'])->toBe('More')
        ->and(collect($more['children'])->firstWhere('type', 'icon')['props']['name'])->toBe('ellipsis')
        ->and(detailTopBarActions($tree))->toBe([]);
})->with([
    'a thing' => ['/inventory/8', ['edit-item', 'item-more']],
    'a place' => ['/inventory/6', ['edit-item', 'item-more']],
    'a recipe' => ['/recipes/1', ['cook-recipe', 'recipe-more']],
    'a list' => ['/lists/1', ['list-add-item', 'list-more']],
    'a routine' => ['/routines/1', ['routine-add-step', 'routine-more']],
]);

it('puts Edit first and Delete last in the more menu, Delete red with a trash icon and nowhere else', function (string $uri, string $more, array $items) {
    $tree = Native::visit($uri, platform: 'ios')->tree();
    $menu = detailMenu($tree, $more);
    $delete = end($menu);

    expect(array_column(array_column($menu, 'props'), 'id'))->toBe($items)
        ->and($menu[0]['props']['label'])->toStartWith('Edit')
        ->and($menu[0]['props']['icon'])->toBe('pencil')
        ->and($delete['props']['destructive'])->toBeTrue()
        ->and($delete['props']['icon'])->toBe('trash')
        ->and(array_filter(array_slice($menu, 0, -1), fn (array $item): bool => $item['props']['destructive'] ?? false))->toBe([])
        ->and(detailNode($tree, fn (array $node): bool => $node['type'] === 'pressable' && ($node['ref'] ?? null) === end($items)))->toBeNull();
})->with([
    'a thing' => ['/inventory/8', 'item-more', ['item-edit', 'item-print-label', 'item-make-copies', 'delete-item']],
    'a place' => ['/inventory/6', 'item-more', ['item-edit', 'item-print-label', 'item-make-copies', 'delete-item']],
    'a recipe' => ['/recipes/1', 'recipe-more', ['recipe-edit', 'recipe-remix', 'recipe-share', 'delete-recipe']],
    'a list' => ['/lists/1', 'list-more', ['edit-list', 'remove-done', 'uncheck-all', 'delete-list']],
    'a routine' => ['/routines/1', 'routine-more', ['edit-routine', 'delete-routine']],
]);

it('leaves Print label out of the menu until the item has synced', function () {
    Item::create([...Item::find(8)->toArray(), 'id' => -1, 'name' => 'New drill']);

    $menu = detailMenu(Native::visit('/inventory/-1', platform: 'ios')->tree(), 'item-more');

    expect(array_column(array_column($menu, 'props'), 'id'))->toBe(['item-edit', 'item-make-copies', 'delete-item']);
});

it('names the kind on the Delete item and the thing in its accessibility label', function (string $uri, string $more, string $label, string $a11yLabel) {
    $menu = detailMenu(Native::visit($uri)->tree(), $more);
    $delete = end($menu);

    expect($delete['props']['label'])->toBe($label)
        ->and($delete['props']['a11y_label'])->toBe($a11yLabel);
})->with([
    'a thing' => ['/inventory/8', 'item-more', 'Delete thing', 'Delete Cordless drill'],
    'storage' => ['/inventory/7', 'item-more', 'Delete storage', 'Delete Tool chest'],
    'a place' => ['/inventory/6', 'item-more', 'Delete place', 'Delete Garage'],
    'a recipe' => ['/recipes/1', 'recipe-more', 'Delete recipe', 'Delete Buttermilk Pancakes'],
    'a list' => ['/lists/1', 'list-more', 'Delete list', 'Delete Groceries'],
    'a routine' => ['/routines/1', 'routine-more', 'Delete routine', 'Delete Get ready'],
]);

it('opens the edit screen from the first item in the more menu', function (string $uri, string $edit) {
    Native::visit($uri)
        ->tap($edit)
        ->assertNavigatedTo($uri.'/edit');
})->with([
    'a thing' => ['/inventory/8', 'item-edit'],
    'a place' => ['/inventory/6', 'item-edit'],
    'a recipe' => ['/recipes/1', 'recipe-edit'],
    'a list' => ['/lists/1', 'edit-list'],
    'a routine' => ['/routines/1', 'edit-routine'],
]);

it('adds a wide Edit shortcut when a recipe has nothing to cook, with Edit still first in the menu', function () {
    Recipe::find(5)->update(['ingredients' => null, 'instructions' => null]);

    $screen = Native::visit('/recipes/5');
    $tree = $screen->tree();
    $edit = detailNode($tree, fn (array $node): bool => ($node['ref'] ?? null) === 'edit-recipe');

    expect(detailBarButtons($tree))->toBe(['edit-recipe', 'recipe-more'])
        ->and($edit['type'])->toBe('pressable')
        ->and($edit['layout']['flex_grow'])->toEqual(1)
        ->and(array_column(array_column(detailMenu($tree, 'recipe-more'), 'props'), 'id'))->toBe(['recipe-edit', 'recipe-remix', 'recipe-share', 'delete-recipe']);

    $screen->tap('edit-recipe')->assertNavigatedTo('/recipes/5/edit');

    Native::visit('/recipes/5')->tap('recipe-edit')->assertNavigatedTo('/recipes/5/edit');
});

it('keeps the plus inside the add field as a row icon', function (string $uri, string $add, string $field) {
    $tree = Native::visit($uri, platform: 'ios')->tree();
    $row = detailNode($tree, fn (array $node): bool => $node['type'] === 'row'
        && array_column($node['children'] ?? [], 'ref') === [$field, $add]);
    $button = $row['children'][1];

    expect($row['props']['glass'])->toBe(1)
        ->and($button['type'])->toBe('pressable')
        ->and($button['style'] ?? null)->toBeNull()
        ->and($button['layout']['width'])->toEqual(48)
        ->and($button['children'][0]['props']['name'])->toBe('plus');
})->with([
    'a list' => ['/lists/1', 'list-add-item', 'list-new-item'],
    'a routine' => ['/routines/1', 'routine-add-step', 'routine-new-step'],
]);
