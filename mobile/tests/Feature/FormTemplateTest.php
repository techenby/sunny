<?php

use Native\Mobile\Testing\Native;

beforeEach(function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
    seedSunnyData();
});

function formNode(array $node, callable $matches): ?array
{
    if ($matches($node)) {
        return $node;
    }

    foreach ($node['children'] ?? [] as $child) {
        if (($found = formNode($child, $matches)) !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * @return list<array<string, mixed>>
 */
function formNodes(array $node, string $type): array
{
    $found = $node['type'] === $type ? [$node] : [];

    foreach ($node['children'] ?? [] as $child) {
        $found = [...$found, ...formNodes($child, $type)];
    }

    return $found;
}

it('pins the submit button where the tab bar was and sets the form at a 16pt margin', function (string $uri, string $submit) {
    $screen = Native::visit($uri, platform: 'ios')->assertTabBarHidden();
    $tree = $screen->tree();
    $bar = formNode($tree, fn (array $node): bool => $node['type'] === 'bottom_bar');
    $page = formNode($tree, fn (array $node): bool => $node['type'] === 'scroll_view');
    $icons = array_values(array_filter(array_map(fn (array $input): ?string => $input['props']['leading_icon'] ?? null, formNodes($tree, 'outlined_text_input'))));

    expect(array_column($bar['children'][0]['children'], 'ref'))->toBe([$submit])
        ->and($bar['children'][0]['children'][0]['layout']['flex_grow'])->toEqual(1)
        ->and(formNode($page, fn (array $node): bool => ($node['ref'] ?? null) === $submit))->toBeNull()
        ->and($page['children'][0]['layout']['padding'][1])->toEqual(16)
        ->and($page['children'][0]['layout']['padding'][3])->toEqual(16)
        ->and(array_diff($icons, ['link']))->toBe([])
        ->and(formNodes($tree, 'button_group'))->toBe([]);
})->with([
    'new list' => ['/lists/create', 'create-list-submit'],
    'edit list' => ['/lists/1/edit', 'edit-list-submit'],
    'new recipe' => ['/recipes/create', 'create-recipe-submit'],
    'edit recipe' => ['/recipes/1/edit', 'edit-recipe-submit'],
    'new routine' => ['/routines/create', 'create-routine-submit'],
    'edit routine' => ['/routines/1/edit', 'edit-routine-submit'],
    'new calendar' => ['/calendar/feeds/create', 'create-calendar-submit'],
    'new thing' => ['/inventory/create', 'create-item-submit'],
    'edit thing' => ['/inventory/8/edit', 'edit-item-submit'],
    'account' => ['/preferences/account', 'account-save'],
    'change password' => ['/preferences/password', 'change-password-save'],
    'new team' => ['/preferences/teams/create', 'create-team-submit'],
]);

it('keeps the tab bar on screens that are not forms', function (string $uri) {
    Native::visit($uri)->assertTabBarVisible();
})->with(['/lists', '/lists/1', '/inventory/8', '/preferences']);

it('shows a date field as its value with a calendar icon, and no Change badge', function () {
    Native::visit('/routines/create', platform: 'ios')
        ->assertDontSee('Change')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-routine-starts-on'
            && str_starts_with($node['props']['a11y_label'], 'Starts on ')
            && array_column($node['children'][0]['children'], 'type') === ['text', 'icon']
            && $node['children'][0]['children'][1]['props']['name'] === 'calendar')
        ->tap('create-routine-starts-on')
        ->assertSet('showStartsOnPicker', true);
});

it('puts helper text below what it explains on the thing form', function () {
    $tree = Native::visit('/inventory/create')->tree();
    $kind = formNode($tree, fn (array $node): bool => $node['type'] === 'column'
        && ($node['children'][0]['props']['text'] ?? null) === 'What kind is it?');

    expect(array_column($kind['children'], 'type'))->toBe(['text', 'column', 'text'])
        ->and($kind['children'][2]['props']['text'])->toBe('Not sure? Leave it as a thing.');
});
