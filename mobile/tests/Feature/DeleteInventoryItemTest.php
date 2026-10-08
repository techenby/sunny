<?php

use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Models\Item;
use App\Models\PendingWrite;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('asks before deleting an item', function () {
    Native::visit('/inventory/8')
        ->tap('delete-item')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'delete-item'
            && $params['message'] === '“Cordless drill” will be deleted for everyone on your team.')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-item'])
        ->assertNoNavigation();

    expect(Item::find(8))->not->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('deletes an item once confirmed', function () {
    Native::visit('/inventory/8')
        ->tap('delete-item')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-item'])
        ->assertWentBack();

    expect(Item::find(8))->toBeNull()
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'deletes'))->toBe(['resource' => 'items', 'record_id' => 8, 'deletes' => true]);
    Native::visit('/inventory/7')->assertDontSee('Cordless drill')->assertSee('Tape measure');
});

it('moves what was inside a deleted container to the top level, like Sunny does', function () {
    app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Socket set', 'type' => 'item', 'parent_id' => 7], 9);

    Native::visit('/inventory/7')
        ->tap('delete-item')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['message']
            === '“Tool chest” will be deleted for everyone on your team. The 2 items inside it will move to the top level.')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-item']);

    expect(Item::find(7))->toBeNull()
        ->and(Item::find(8)->parent_id)->toBeNull()
        ->and(Item::find(9)->parent_id)->toBeNull()
        ->and(PendingWrite::query()->where('record_id', 9)->sole()->payload['parent_id'])->toBeNull();
    Native::visit('/inventory')->assertSee('Cordless drill')->assertSee('Socket set');
});

it('deletes an item that never reached Sunny without queueing anything', function () {
    $id = app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Ladder', 'type' => 'item', 'parent_id' => 6]);

    Native::visit('/inventory/'.$id)
        ->tap('delete-item')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-item'])
        ->assertWentBack();

    expect(Item::find($id))->toBeNull()->and(PendingWrite::count())->toBe(0);
});
