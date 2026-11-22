<?php

use App\Http\Integrations\Sunny\Requests\DeleteRecordRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Item;
use App\Models\PendingWrite;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

it('picks things from a place and puts the page back when done', function () {
    Native::visit('/inventory/6')
        ->assertTabBarVisible()
        ->tap('item-select')
        ->assertSet('selecting', true)
        ->assertTabBarHidden()
        ->assertSee('Nothing picked yet')
        ->assertMissingElement('pressable', fn (array $node): bool => in_array($node['ref'] ?? null, ['item-add', 'item-more', 'edit-item'], true))
        ->assertMissingElement('top_bar_action', fn (array $node): bool => ($node['ref'] ?? null) === 'delete-item')
        ->tap('item-child-10')
        ->assertNoNavigation()
        ->assertSet('selectedIds', [10])
        ->assertSee('1 thing picked')
        ->tap('item-child-7')
        ->assertSee('2 things picked')
        ->tap('item-child-10')
        ->assertSet('selectedIds', [7])
        ->tap('item-select-done')
        ->assertSet('selecting', false)
        ->assertSet('selectedIds', [])
        ->assertTabBarVisible()
        ->assertElement('top_bar_action', fn (array $node): bool => ($node['ref'] ?? null) === 'delete-item');
});

it('only offers Select when something is inside', function () {
    Native::visit('/inventory/10')->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-select');
});

it('selects everything inside and clears it again', function () {
    Native::visit('/inventory/6')
        ->tap('item-select')
        ->assertSee('Select all')
        ->tap('selection-all')
        ->assertSet('selectedIds', [10, 7])
        ->assertSee('2 things picked')
        ->assertSee('Deselect all')
        ->tap('selection-all')
        ->assertSet('selectedIds', []);
});

it('moves the picked things into another container', function () {
    $screen = Native::visit('/inventory/6')
        ->tap('item-select')
        ->tap('selection-all')
        ->tap('selection-move')
        ->assertSet('showParentPicker', true)
        ->assertSet('parentBrowseId', null)
        ->assertSee('Where do they go?')
        ->assertSee('Moving Camping tent and Tool chest')
        ->assertSee('They’re here now')
        ->assertSee('Move 2 to Not in a place yet');

    expect(array_column($screen->get('parentChoices'), 'name'))->toBe([
        'Basement', 'Holiday decorations', 'Ornaments', 'String lights', 'Spare light bulbs',
        'Garage',
        'Kitchen', 'Pantry', 'Basmati rice', 'Canned tomatoes', 'Olive oil',
    ]);

    $screen->tap('move-parent-11')
        ->assertSee('Move 2 to Basement')
        ->tap('move-parent-choose')
        ->assertSet('showParentPicker', false)
        ->assertSet('selecting', false)
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Moved 2 things to Basement' && $params['variant'] === 'success')
        ->assertMissingElement('pressable', fn (array $node): bool => in_array($node['ref'] ?? null, ['item-child-7', 'item-child-10'], true));

    expect(Item::find(7)->parent_id)->toBe(11)
        ->and(Item::find(10)->parent_id)->toBe(11)
        ->and(Item::find(8)->parent_id)->toBe(7)
        ->and(PendingWrite::query()->orderBy('record_id')->get()->map(fn (PendingWrite $write): array => [$write->record_id, $write->payload['parent_id']])->all())
        ->toBe([[7, 11], [10, 11]]);
});

it('never moves a picked container into itself or anything inside it', function () {
    Native::visit('/inventory/6')
        ->tap('item-select')
        ->tap('item-child-7')
        ->tap('selection-move')
        ->call('selectParent', 8)
        ->tap('selection-move')
        ->call('selectParent', 7);

    expect(Item::find(7)->parent_id)->toBe(6)
        ->and(PendingWrite::count())->toBe(0);
});

it('takes one picked thing out of every place', function () {
    Native::visit('/inventory/6')
        ->tap('item-select')
        ->tap('item-child-10')
        ->tap('selection-move')
        ->assertSee('Where does it go?')
        ->assertSee('Moving Camping tent')
        ->assertSee('Don’t put it in a place yet')
        ->tap('move-parent-choose')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Moved 1 thing to “Not in a place yet”' && $params['variant'] === 'success');

    expect(Item::find(10)->parent_id)->toBeNull();
});

it('leaves the single Move for the page itself', function () {
    Native::visit('/inventory/7')
        ->tap('item-select')
        ->tap('item-child-8')
        ->tap('selection-move')
        ->tap('move-parent-cancel')
        ->tap('item-move')
        ->assertSee('Where does it go?')
        ->assertDontSee('Moving Cordless drill');

    expect(array_column(Native::visit('/inventory/7')->tap('item-move')->get('parentChoices'), 'id'))->not->toContain(7, 8, 9);
});

it('asks before deleting the picked things', function (array $picks, string $title, string $message) {
    $screen = Native::visit('/inventory/6')->tap('item-select');

    foreach ($picks as $id) {
        $screen->tap('item-child-'.$id);
    }

    $screen->tap('selection-delete')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'delete-selected'
            && $params['title'] === $title
            && $params['message'] === $message)
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-selected'])
        ->assertSet('selecting', true);

    expect(Item::find(7))->not->toBeNull()
        ->and(Item::find(10))->not->toBeNull()
        ->and(PendingWrite::count())->toBe(0);
})->with([
    'one' => [[10], 'Delete 1 thing?', 'The 1 thing you selected will be deleted for everyone on your team. Anything inside it will move to “Not in a place yet”.'],
    'several' => [[10, 7], 'Delete 2 things?', 'The 2 things you selected will be deleted for everyone on your team. Anything inside them will move to “Not in a place yet”.'],
]);

it('deletes the picked things once confirmed', function () {
    Native::visit('/inventory/6')
        ->tap('item-select')
        ->tap('selection-all')
        ->tap('selection-delete')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-selected'])
        ->assertNoNavigation()
        ->assertSet('selecting', false)
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Deleted 2 things' && $params['variant'] === 'success')
        ->assertMissingElement('pressable', fn (array $node): bool => in_array($node['ref'] ?? null, ['item-child-7', 'item-child-10'], true));

    expect(Item::find(7))->toBeNull()
        ->and(Item::find(10))->toBeNull()
        ->and(Item::onlyTrashed()->orderBy('id')->pluck('id')->all())->toBe([7, 10])
        ->and(Item::find(8)->parent_id)->toBeNull()
        ->and(Item::find(9)->parent_id)->toBeNull()
        ->and(PendingWrite::query()->orderBy('record_id')->get()->map->only('record_id', 'deletes')->all())
        ->toBe([['record_id' => 7, 'deletes' => true], ['record_id' => 10, 'deletes' => true]]);
});

it('keeps bulk changes on the phone until Sunny can be reached', function () {
    Saloon::fake([
        SaveRecordRequest::class => MockResponse::make([], 503),
        DeleteRecordRequest::class => MockResponse::make([], 503),
    ]);

    Native::visit('/inventory/11')
        ->tap('item-select')
        ->tap('selection-all')
        ->tap('selection-move')
        ->tap('move-parent-6')
        ->tap('move-parent-choose');

    Native::visit('/inventory/6')
        ->assertSee('Holiday decorations')
        ->assertSee('Spare light bulbs')
        ->tap('item-select')
        ->tap('item-child-12')
        ->tap('item-child-7')
        ->tap('selection-delete')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-selected']);

    expect(fn () => app(SunnyWrites::class)->push())->toThrow(RequestException::class);
    expect(PendingWrite::query()->orderBy('record_id')->get()->map->only('record_id', 'deletes', 'error')->all())->toBe([
        ['record_id' => 7, 'deletes' => true, 'error' => null],
        ['record_id' => 12, 'deletes' => true, 'error' => null],
        ['record_id' => 15, 'deletes' => false, 'error' => null],
    ])->and(Item::find(15)->parent_id)->toBe(6)
        ->and(app(SunnySyncCoordinator::class)->isDue())->toBeTrue();

    Native::visit('/inventory/6')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-child-15')
        ->assertMissingElement('pressable', fn (array $node): bool => in_array($node['ref'] ?? null, ['item-child-7', 'item-child-12'], true));
});
