<?php

use App\Http\Integrations\Sunny\Requests\GetTeamInvitationsRequest;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([GetTeamInvitationsRequest::class => fn () => MockResponse::make(['data' => []])]);

    Team::find(1)->update(['members' => [
        ['id' => 7, 'name' => 'Andy Swick', 'initials' => 'AS', 'role' => 'owner'],
        ['id' => 10, 'name' => 'Grandma Jo', 'initials' => 'GJ', 'role' => 'member'],
    ]]);
    DB::table('sunny_accounts')->updateOrInsert(['server' => SunnyStore::server()], ['user_id' => 7]);
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Lake Cabin', 'slug' => 'lake-cabin']);
});

function sheetNode(array $node, string $ref): ?array
{
    if ($node['type'] === 'bottom_sheet' && ($node['ref'] ?? null) === $ref) {
        return $node;
    }

    foreach ($node['children'] ?? [] as $child) {
        if (($found = sheetNode($child, $ref)) !== null) {
            return $found;
        }
    }

    return null;
}

/**
 * @return list<array<string, mixed>>
 */
function sheetNodes(array $node): array
{
    return [$node, ...array_merge([], ...array_map(sheetNodes(...), $node['children'] ?? []))];
}

it('gives every sheet the one header, height and confirm button', function (string $uri, string $action, string $target, string $sheetRef, string $closeRef, string $title, string $detents, ?string $confirmRef) {
    $screen = Native::visit($uri, platform: 'ios');

    expect(sheetNode($screen->tree(), $sheetRef)['props']['visible'])->toBeFalse();

    $screen->{$action}($target);

    $sheet = sheetNode($screen->tree(), $sheetRef);
    $root = $sheet['children'][0];
    $backdrop = $root['children'][0];
    $header = $root['children'][1];
    $nodes = sheetNodes($sheet);
    $texts = array_values(array_filter(array_map(fn (array $node): ?string => $node['type'] === 'text' ? $node['props']['text'] : null, $nodes)));
    $primaryButtons = array_values(array_filter($nodes, fn (array $node): bool => in_array($node['type'], ['pressable', 'column'], true)
        && ($node['style']['bg_color'] ?? null) === theme('primary')
        && ($node['layout']['min_height'] ?? null) == 48));
    $chevrons = array_filter($nodes, fn (array $node): bool => $node['type'] === 'icon' && $node['props']['name'] === 'chevron.right');

    expect($sheet['props']['visible'])->toBeTrue()
        ->and($sheet['props']['detents'])->toBe($detents)
        ->and($sheet['children'])->toHaveCount(1)
        ->and($root['style']['bg_color'])->toBe(theme('background'))
        ->and($backdrop['layout']['position_type'])->toBe(1)
        ->and($backdrop['layout']['position'])->toEqual([0, 0, -1000, 0])
        ->and($backdrop['style']['bg_color'])->toBe(theme('background'))
        ->and(array_column($header['children'], 'type'))->toBe(['pressable', 'text', 'column'])
        ->and($header['children'][0]['ref'])->toBe($closeRef)
        ->and($header['children'][0]['props']['a11y_label'])->toBe('Close')
        ->and($header['children'][0]['props'])->toHaveKey('glass')
        ->and($header['children'][0]['layout']['width'])->toEqual(48)
        ->and($header['children'][0]['children'][0]['props']['name'])->toBe('xmark')
        ->and($header['children'][1]['props']['text'])->toBe($title)
        ->and($header['children'][1]['props']['text_align'])->toEqual(1)
        ->and(array_values(array_filter($texts, fn (string $text): bool => preg_match('/^(Cancel|Done|Not now|Keep\b.*)$/', $text) === 1)))->toBe([])
        ->and(array_column($primaryButtons, 'ref'))->toBe($confirmRef === null ? [] : [$confirmRef])
        ->and($chevrons !== [])->toBe(str_ends_with($sheetRef, '-parent-picker'));

    if ($confirmRef !== null) {
        $confirm = $root['children'][count($root['children']) - 1];

        expect($confirm['children'][0]['ref'])->toBe($confirmRef)
            ->and($confirm['children'][0]['type'])->toBe('pressable')
            ->and($confirm['children'][0]['layout']['width'])->toBe('fill')
            ->and($confirm['layout']['padding'])->toEqual([24, 16, 0, 16]);
    }

    $body = $root['children'][count($root['children']) - 1];

    expect($body['layout']['padding'][2] ?? 0)->toEqual(0);

    $screen->assertAccessible()->tap($closeRef);

    expect(sheetNode($screen->tree(), $sheetRef)['props']['visible'])->toBeFalse();

    $screen->{$action}($target)->dismissSheet($sheetRef);

    expect(sheetNode($screen->tree(), $sheetRef)['props']['visible'])->toBeFalse();
})->with([
    'rename team' => ['/preferences/team', 'tap', 'team-name', 'team-rename-sheet', 'team-rename-close', 'Rename Family', 'fit', 'team-rename-save'],
    'timezone' => ['/preferences/team', 'tap', 'team-timezone', 'team-timezone-sheet', 'team-timezone-close', 'Timezone', 'large', null],
    'team member' => ['/preferences/team', 'tap', 'member-10', 'team-member-sheet', 'member-close', 'Grandma Jo', 'fit', null],
    'make copies' => ['/inventory/12', 'tap', 'Make copies', 'item-copies-sheet', 'item-copies-close', 'Make copies', 'fit', 'item-copies-submit'],
    'move to another team' => ['/inventory/12', 'tap', 'Move to another team', 'item-team-move-sheet', 'item-team-move-close', 'Move to another team', 'fit', 'item-team-move-submit'],
    'where does it go, moving' => ['/inventory/7', 'tap', 'item-move', 'move-parent-picker', 'move-parent-close', 'Where does it go?', 'large', 'move-parent-choose'],
    'where does it go, new thing' => ['/inventory/create', 'tap', 'create-item-parent', 'create-item-parent-picker', 'create-item-parent-close', 'Where does it go?', 'large', 'create-item-parent-choose'],
    'where does it go, editing' => ['/inventory/8/edit', 'tap', 'edit-item-parent', 'edit-item-parent-picker', 'edit-item-parent-close', 'Where does it go?', 'large', 'edit-item-parent-choose'],
    'copy recipe to a team' => ['/recipes/1', 'tap', 'Copy to another team', 'recipe-copy-sheet', 'recipe-copy-close', 'Copy to another team', 'fit', 'recipe-copy-submit'],
    'share recipe' => ['/recipes/1', 'tap', 'Share', 'recipe-share-sheet', 'recipe-share-close', 'Share recipe', 'fit', 'recipe-share-submit'],
    'new routine start date' => ['/routines/create', 'tap', 'create-routine-starts-on', 'create-routine-starts-on-picker', 'create-routine-starts-on-close', 'Starts on', 'fit', null],
    'routine start date' => ['/routines/1/edit', 'tap', 'edit-routine-starts-on', 'edit-routine-starts-on-picker', 'edit-routine-starts-on-close', 'Starts on', 'fit', null],
    'edit a list item' => ['/lists/1', 'press', 'editItem(1)', 'list-edit-sheet', 'list-edit-close', 'Edit item', 'fit', 'list-edit-save'],
]);

it('closes on a pick that is a setting, with no button', function () {
    Native::visit('/routines/create')
        ->tap('create-routine-starts-on')
        ->pickDate('create-routine-starts-on-calendar', '2026-12-01')
        ->assertSet('showStartsOnPicker', false);
});

it('marks a pick that moves or copies something and waits for the button', function (string $uri, string $open, string $pick, string $picked, string $submit, string $visible) {
    Native::visit($uri)
        ->tap($open)
        ->tap($pick)
        ->assertSet($visible, true)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === $pick
            && str_ends_with($node['props']['a11y_label'], ', selected')
            && collect($node['children'][0]['children'])->contains(fn (array $child): bool => $child['type'] === 'icon'))
        ->assertSee($picked)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === $submit);
})->with([
    'move to another team' => ['/inventory/12', 'Move to another team', 'item-team-move-team-2', 'Move to Lake Cabin', 'item-team-move-submit', 'showTeamMove'],
    'copy recipe to a team' => ['/recipes/1', 'Copy to another team', 'recipe-copy-team-2', 'Copy to Lake Cabin', 'recipe-copy-submit', 'showCopy'],
]);
