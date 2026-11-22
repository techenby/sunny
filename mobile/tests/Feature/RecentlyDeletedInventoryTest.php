<?php

use App\Http\Integrations\Sunny\Requests\DeleteRecordRequest;
use App\Http\Integrations\Sunny\Requests\ForceDeleteItemRequest;
use App\Http\Integrations\Sunny\Requests\RestoreItemRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnyThumbnails;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\PendingWrite;
use App\Models\Team;
use App\NativeComponents\Inventory;
use App\NativeComponents\OpenItemLink;
use App\NativeComponents\RecentlyDeletedInventory;
use Illuminate\Support\Facades\File;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Victorycodedev\ToastKit\Events\ToastActionPressed;

function deletedSunnyItem(int $id, string $name, string $type, ?int $parentId, string $deletedAt, array $extra = []): array
{
    return [
        'id' => $id, 'team_id' => 1, 'parent_id' => $parentId, 'type' => $type, 'name' => $name, 'metadata' => null,
        'photo_url' => null, 'thumb_url' => null, 'photos' => [],
        'created_at' => '2026-03-01T10:00:00Z', 'updated_at' => $deletedAt, 'deleted_at' => $deletedAt, ...$extra,
    ];
}

function seedDeletedSunnyItems(): void
{
    $store = app(SunnyStore::class);
    $store->saveRecord('items', deletedSunnyItem(20, 'Old lamp', 'item', 6, '2026-11-17T09:00:00Z', [
        'photo_url' => 'https://sunny.example/photos/701.jpg',
        'photos' => [['id' => 701, 'item_id' => 20, 'position' => 0, 'url' => 'https://sunny.example/photos/701.jpg', 'thumb_url' => null, 'created_at' => '2026-11-01T10:00:00Z', 'updated_at' => '2026-11-01T10:00:00Z']],
    ]));
    $store->saveRecord('items', deletedSunnyItem(21, 'Toy bin', 'bin', 11, '2026-11-16T18:00:00Z'));
    $store->saveRecord('items', deletedSunnyItem(22, 'Bike pump', 'item', 6, '2026-11-12T12:00:00Z'));
}

function sunnySnapshotWith(array $items): array
{
    $fixture = json_decode(file_get_contents(__DIR__.'/../Fixtures/sunny.json'), true, flags: JSON_THROW_ON_ERROR);

    return [
        ...$fixture,
        'recipes' => array_map(fn (array $record): array => $record + ['team_id' => 1], $fixture['recipes']),
        'items' => [...array_map(fn (array $record): array => $record + ['team_id' => 1], $fixture['items']), ...$items],
        'checklists' => array_map(fn (array $record): array => $record + ['team_id' => 1], $fixture['checklists']),
        'routines' => array_map(fn (array $record): array => $record + ['team_id' => 1], $fixture['routines']),
        'routine_occurrences' => [],
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'synced_at' => now()->toIso8601String(),
    ];
}

beforeEach(function (): void {
    $this->travelTo('2026-11-17 15:00:00');
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

it('shows the way into Recently deleted only while something is deleted', function () {
    Native::visit('/inventory')
        ->assertDontSee('Recently deleted')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'inventory-recently-deleted');

    seedDeletedSunnyItems();

    Native::visit('/inventory')
        ->assertSet('deletedCount', 3)
        ->assertSee('Recently deleted')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'inventory-recently-deleted')
        ->assertAccessible()
        ->input('updateSearch', 'drill')
        ->assertDontSee('Recently deleted')
        ->input('updateSearch', '')
        ->tap('inventory-recently-deleted')
        ->assertNavigatedTo('/inventory/deleted')
        ->follow()
        ->assertScreen(RecentlyDeletedInventory::class)
        ->assertNavTitle('Recently deleted');

    $outbox = app(SunnyOutbox::class);
    $outbox->restore(1, 20);
    $outbox->restore(1, 21);
    $outbox->forceDelete(1, 22);

    Native::visit('/inventory')
        ->assertSet('deletedCount', 0)
        ->assertDontSee('Recently deleted');
});

it('lists deleted things newest first with their type, where they were and when', function () {
    seedDeletedSunnyItems();
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(23, 'Attic', 'location', null, '2025-10-01T12:00:00Z'));
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(24, 'Sled', 'item', null, '2026-11-12T12:00:00Z'));

    $screen = Native::visit('/inventory/deleted')
        ->assertSee('Nothing here goes away on its own.')
        ->assertSee('Anything inside went to “Not in a place yet”, so it comes back empty.')
        ->assertSee('Restore puts it back where it was. If that place was deleted too, it comes back under “Not in a place yet”.')
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === 'https://sunny.example/photos/701.jpg')
        ->assertAccessible();

    expect(collect($screen->get('items'))->map(fn (array $item): array => [$item['name'], $item['supporting'], $item['deleted'], $item['emptied']])->all())->toBe([
        ['Old lamp', 'Thing · was in Garage', 'Deleted today', false],
        ['Toy bin', 'Storage · was in Basement', 'Deleted yesterday', true],
        ['Bike pump', 'Thing · was in Garage', 'Deleted 12 Nov', false],
        ['Sled', 'Thing · wasn’t in a place', 'Deleted 12 Nov', false],
        ['Attic', 'Place', 'Deleted 1 Oct 2025', true],
    ]);
});

it('gives each deleted thing a compact row with Restore and a more menu that holds Delete forever', function () {
    seedDeletedSunnyItems();

    Native::visit('/inventory/deleted', platform: 'ios')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'deleted-restore-20'
            && $node['style']['bg_color'] === theme('surface-variant')
            && ($node['layout']['width'] ?? null) !== 'fill'
            && $node['children'][0]['props']['text'] === 'Restore')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'deleted-more-20'
            && ! isset($node['style'])
            && $node['layout']['width'] == 48
            && str_starts_with($node['props']['a11y_label'], 'More for ')
            && collect($node['children'])->where('type', 'top_bar_action')->map(fn (array $item): array => [$item['ref'], $item['props']['label'], $item['props']['destructive'], $item['props']['icon']])->values()->all() === [['deleted-forever-20', 'Delete forever', true, 'trash']])
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'deleted-forever-20');
});

it('counts days in the team’s time zone', function () {
    seedDeletedSunnyItems();
    Team::find(1)->update(['timezone' => 'Pacific/Auckland']);

    $screen = Native::visit('/inventory/deleted');

    expect(array_column($screen->get('items'), 'deleted'))->toBe(['Deleted yesterday', 'Deleted yesterday', 'Deleted 13 Nov']);
});

it('shows only the active team’s deleted things', function () {
    seedDeletedSunnyItems();
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Work', 'slug' => 'work']);
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(30, 'Office chair', 'item', null, '2026-11-17T09:00:00Z', ['team_id' => 2]));

    Native::visit('/inventory/deleted')->assertDontSee('Office chair');
    Native::visit('/inventory')->assertSet('deletedCount', 3);
});

it('says when nothing is deleted', function () {
    Native::visit('/inventory/deleted')
        ->assertSee('Nothing deleted')
        ->assertSee('Everything’s been restored or deleted forever. Things you delete will wait here until you decide.')
        ->assertMissingElement('scroll-view', fn (array $node): bool => ($node['ref'] ?? null) === 'deleted-list')
        ->assertAccessible();
});

it('restores a thing to where it was on the phone, then confirms it with Sunny', function () {
    seedDeletedSunnyItems();

    Native::visit('/inventory/deleted')
        ->tap('deleted-restore-22')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Restored Bike pump to Garage' && $params['variant'] === 'success'
            && $params['action'] === ['id' => 'go-to-restored', 'label' => 'Go there'] && $params['data'] === ['url' => '/inventory/6'])
        ->assertDontSee('Bike pump forever')
        ->emitNative(ToastActionPressed::class, ['toastId' => 'restored', 'actionId' => 'go-to-restored', 'data' => ['url' => '/inventory/6']])
        ->assertNavigatedTo('/inventory/6');

    expect(Item::find(22))->parent_id->toBe(6)->deleted_at->toBeNull()
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'deletes', 'payload'))->toBe(['resource' => 'items', 'record_id' => 22, 'deletes' => false, 'payload' => ['restore' => true]]);
    Native::visit('/inventory/6')->assertSee('Bike pump');

    Saloon::fake([RestoreItemRequest::class => MockResponse::make(['data' => [...deletedSunnyItem(22, 'Bike pump', 'item', 6, '2026-11-17T15:00:00Z'), 'deleted_at' => null]])]);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (RestoreItemRequest $request): bool => $request->resolveEndpoint() === '/teams/family/items/22/restore' && $request->getMethod() === Method::POST);
    expect(PendingWrite::count())->toBe(0)->and(Item::find(22)->parent_id)->toBe(6);
});

it('restores while Sunny can’t be reached and sends it later', function () {
    seedDeletedSunnyItems();
    Saloon::fake([RestoreItemRequest::class => MockResponse::make([], 503)]);

    Native::visit('/inventory/deleted')
        ->tap('deleted-restore-20')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Restored Old lamp to Garage');

    expect(fn () => app(SunnyWrites::class)->push())->toThrow(RequestException::class);
    expect(PendingWrite::sole()->only('payload', 'error'))->toBe(['payload' => ['restore' => true], 'error' => null]);

    Saloon::fake([SyncRequest::class => MockResponse::make(sunnySnapshotWith([deletedSunnyItem(20, 'Old lamp', 'item', 6, '2026-11-17T09:00:00Z')]))]);
    app(SunnySync::class)->sync();
    expect(Item::find(20))->not->toBeNull();

    Saloon::fake([RestoreItemRequest::class => MockResponse::make(['data' => [...deletedSunnyItem(20, 'Old lamp', 'item', 6, '2026-11-17T15:00:00Z'), 'deleted_at' => null]])]);
    app(SunnyWrites::class)->push();
    expect(PendingWrite::count())->toBe(0)->and(Item::find(20))->not->toBeNull();
});

it('brings a thing back to the top level when its place is still deleted, like Sunny does', function () {
    seedDeletedSunnyItems();
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(25, 'Lego', 'item', 21, '2026-11-16T17:00:00Z'));
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(26, 'Shed', 'location', 21, '2026-11-16T17:00:00Z'));

    Native::visit('/inventory/deleted')
        ->assertSee('Thing · was in Toy bin')
        ->tap('deleted-restore-25')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Restored Lego to “Not in a place yet”' && $params['data'] === ['url' => '/inventory'])
        ->emitNative(ToastActionPressed::class, ['toastId' => 'restored', 'actionId' => 'go-to-restored', 'data' => ['url' => '/inventory']])
        ->assertWentBack();

    Native::visit('/inventory/deleted')
        ->tap('deleted-restore-26')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Restored Shed to your home');

    expect(Item::find(25)->parent_id)->toBeNull()->and(Item::find(26)->parent_id)->toBeNull();
    $inventory = Native::visit('/inventory')->assertSee('Lego');
    expect(array_column($inventory->get('unplaced'), 'name'))->toContain('Lego')
        ->and(array_column($inventory->get('places'), 'name'))->toContain('Shed');
});

it('counts a restore Sunny no longer has to do as done', function (int $status) {
    seedDeletedSunnyItems();
    Saloon::fake([RestoreItemRequest::class => MockResponse::make([], $status)]);

    app(SunnyOutbox::class)->restore(1, 22);
    app(SunnyWrites::class)->push();

    expect(PendingWrite::count())->toBe(0)->and(Item::find(22))->not->toBeNull();
})->with([403, 404]);

it('sends an edit made after restoring once the restore is through', function () {
    seedDeletedSunnyItems();
    $outbox = app(SunnyOutbox::class);
    $outbox->restore(1, 22);
    $outbox->queue('items', 1, ['name' => 'Floor pump', 'type' => 'item', 'parent_id' => 6, 'metadata' => null], 22);
    $sent = [];
    Saloon::fake([
        RestoreItemRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = 'restore';

            return MockResponse::make(['data' => [...deletedSunnyItem(22, 'Bike pump', 'item', 6, '2026-11-17T15:00:00Z'), 'deleted_at' => null]]);
        },
        SaveRecordRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = 'save '.$pending->body()->get('name');

            return MockResponse::make(['data' => [...deletedSunnyItem(22, 'Floor pump', 'item', 6, '2026-11-17T15:00:00Z'), 'deleted_at' => null]]);
        },
    ]);

    app(SunnyWrites::class)->push();

    expect($sent)->toBe(['restore', 'save Floor pump'])
        ->and(PendingWrite::count())->toBe(0)
        ->and(Item::find(22)->name)->toBe('Floor pump');
});

it('restores a thing whose deletion never left the phone without deleting it on Sunny', function () {
    Saloon::fake([RestoreItemRequest::class => MockResponse::make([], 404), DeleteRecordRequest::class => MockResponse::make([], 204)]);
    $outbox = app(SunnyOutbox::class);
    $outbox->delete('items', 1, 10);

    Native::visit('/inventory/deleted')->assertSee('Camping tent')->tap('deleted-restore-10');
    app(SunnyWrites::class)->push();

    Saloon::assertNotSent(DeleteRecordRequest::class);
    expect(PendingWrite::count())->toBe(0)->and(Item::find(10)->parent_id)->toBe(6);
});

it('asks before deleting forever', function () {
    seedDeletedSunnyItems();

    Native::visit('/inventory/deleted')
        ->tap('deleted-forever-20')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'delete-forever-20'
            && $params['title'] === 'Delete Old lamp forever?'
            && $params['message'] === '“Old lamp” and its photos will be deleted for everyone on your team. You can’t undo this.')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-forever-20'])
        ->assertSee('Old lamp');

    expect(Item::onlyTrashed()->find(20))->not->toBeNull()->and(PendingWrite::count())->toBe(0);

    Native::visit('/inventory/deleted')
        ->tap('deleted-forever-22')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['message'] === '“Bike pump” will be deleted for everyone on your team. You can’t undo this.');
});

it('deletes forever once confirmed, with its photos', function () {
    seedDeletedSunnyItems();
    $thumb = SunnyThumbnails::pathFor('https://sunny.example/thumbs/701.jpg');
    File::ensureDirectoryExists(SunnyThumbnails::directory());
    File::put($thumb, 'thumb');
    ItemPhoto::find(701)->update(['thumb_url' => 'https://sunny.example/thumbs/701.jpg', 'thumb_path' => $thumb]);
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(25, 'Lamp shade', 'item', 20, '2026-11-17T08:00:00Z'));

    Native::visit('/inventory/deleted')
        ->tap('deleted-forever-20')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-forever-20'])
        ->assertDontSee('Old lamp')
        ->assertSee('Thing · wasn’t in a place');

    expect(Item::withTrashed()->find(20))->toBeNull()
        ->and(ItemPhoto::query()->where('item_id', 20)->exists())->toBeFalse()
        ->and($thumb)->not->toBeFile()
        ->and(Item::onlyTrashed()->find(25)->parent_id)->toBeNull()
        ->and(PendingWrite::sole()->only('record_id', 'deletes', 'payload'))->toBe(['record_id' => 20, 'deletes' => true, 'payload' => ['forever' => true]]);

    Saloon::fake([ForceDeleteItemRequest::class => MockResponse::make([], 204)]);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (ForceDeleteItemRequest $request): bool => $request->resolveEndpoint() === '/teams/family/items/20/force' && $request->getMethod() === Method::DELETE);
    expect(PendingWrite::count())->toBe(0);
});

it('deletes forever while Sunny can’t be reached and doesn’t bring it back on the next download', function () {
    seedDeletedSunnyItems();
    Saloon::fake([ForceDeleteItemRequest::class => MockResponse::make([], 503)]);
    app(SunnyOutbox::class)->forceDelete(1, 22);

    expect(fn () => app(SunnyWrites::class)->push())->toThrow(RequestException::class);
    expect(PendingWrite::sole()->only('deletes', 'error'))->toBe(['deletes' => true, 'error' => null]);

    Saloon::fake([SyncRequest::class => MockResponse::make(sunnySnapshotWith([deletedSunnyItem(22, 'Bike pump', 'item', 6, '2026-11-12T12:00:00Z')]))]);
    app(SunnySync::class)->sync();
    expect(Item::withTrashed()->find(22))->toBeNull();
    Native::visit('/inventory/deleted')->assertDontSee('Bike pump');

    Saloon::fake([ForceDeleteItemRequest::class => MockResponse::make([], 204)]);
    app(SunnyWrites::class)->push();
    expect(PendingWrite::count())->toBe(0);
});

it('counts deleting forever as done when Sunny no longer has it', function (int $status) {
    seedDeletedSunnyItems();
    Saloon::fake([ForceDeleteItemRequest::class => MockResponse::make([], $status)]);

    app(SunnyOutbox::class)->forceDelete(1, 22);
    app(SunnyWrites::class)->push();

    expect(PendingWrite::count())->toBe(0);
})->with([403, 404]);

it('deletes on Sunny first when the thing was deleted forever before its deletion was sent', function () {
    $sent = [];
    Saloon::fake([
        DeleteRecordRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = $pending->getRequest()->resolveEndpoint();

            return MockResponse::make([], 204);
        },
        ForceDeleteItemRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = $pending->getRequest()->resolveEndpoint();

            return MockResponse::make([], 204);
        },
    ]);
    $outbox = app(SunnyOutbox::class);
    $outbox->delete('items', 1, 10);
    $outbox->forceDelete(1, 10);

    app(SunnyWrites::class)->push();

    expect($sent)->toBe(['/teams/family/items/10', '/teams/family/items/10/force'])
        ->and(PendingWrite::count())->toBe(0)
        ->and(Item::withTrashed()->find(10))->toBeNull();
});

it('keeps a thing deleted on the phone in Recently deleted with its photos', function () {
    app(SunnyStore::class)->saveRecord('items', [...Item::find(7)->toArray(), 'photos' => [
        ['id' => 702, 'item_id' => 7, 'position' => 0, 'url' => 'https://sunny.example/photos/702.jpg', 'thumb_url' => null, 'created_at' => '2026-11-01T10:00:00Z', 'updated_at' => '2026-11-01T10:00:00Z'],
    ]]);

    Native::visit('/inventory/7')
        ->tap('delete-item')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-item']);

    expect(Item::onlyTrashed()->find(7))->parent_id->toBe(6)->deleted_at->not->toBeNull()
        ->and(ItemPhoto::query()->where('item_id', 7)->pluck('id')->all())->toBe([702]);
    Native::visit('/inventory/deleted')
        ->assertSee('Tool chest')
        ->assertSee('Storage · was in Garage')
        ->assertSee('Deleted today');
});

it('keeps deleted things and their photos from Sunny, and drops them once Sunny deletes them forever', function () {
    Saloon::fake([SyncRequest::class => MockResponse::make(sunnySnapshotWith([
        deletedSunnyItem(20, 'Old lamp', 'item', 6, '2026-11-17T09:00:00Z', ['photos' => [
            ['id' => 701, 'item_id' => 20, 'position' => 0, 'url' => 'https://sunny.example/photos/701.jpg', 'thumb_url' => null, 'created_at' => '2026-11-01T10:00:00Z', 'updated_at' => '2026-11-01T10:00:00Z'],
        ]]),
    ]))]);
    app(SunnySync::class)->sync();

    expect(Item::find(20))->toBeNull()
        ->and(Item::onlyTrashed()->find(20)->deleted_at->toIso8601String())->toBe('2026-11-17T09:00:00+00:00')
        ->and(ItemPhoto::query()->where('item_id', 20)->pluck('id')->all())->toBe([701]);

    Saloon::fake([SyncRequest::class => MockResponse::make(sunnySnapshotWith([]))]);
    app(SunnySync::class)->sync();

    expect(Item::withTrashed()->find(20))->toBeNull()
        ->and(ItemPhoto::query()->where('item_id', 20)->exists())->toBeFalse();
});

it('puts a thing back in Recently deleted when Sunny deletes it and takes it out when Sunny restores it', function () {
    $snapshot = sunnySnapshotWith([]);
    $snapshot['items'][9]['deleted_at'] = '2026-11-17T09:00:00Z';
    Saloon::fake([SyncRequest::class => MockResponse::make($snapshot)]);
    app(SunnySync::class)->sync();

    expect(Item::find(10))->toBeNull()->and(Item::onlyTrashed()->find(10))->not->toBeNull();

    Saloon::fake([SyncRequest::class => MockResponse::make(sunnySnapshotWith([]))]);
    app(SunnySync::class)->sync();

    expect(Item::find(10))->not->toBeNull();
});

it('leaves deleted things out of everything but Recently deleted', function () {
    seedDeletedSunnyItems();
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(25, 'Lamp shade', 'item', 6, '2026-11-17T08:00:00Z', ['updated_at' => '2026-11-17T14:59:00Z']));
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(26, 'Attic', 'location', null, '2026-11-17T08:00:00Z', ['updated_at' => '2026-11-17T14:59:00Z']));
    app(SunnyStore::class)->saveRecord('items', deletedSunnyItem(27, 'Loose lamp', 'item', null, '2026-11-17T08:00:00Z', ['updated_at' => '2026-11-17T14:59:00Z']));

    expect(array_column(Inventory::all(), 'id'))->not->toContain(20, 21, 22, 25, 26, 27)
        ->and(Inventory::find(20))->toBeNull()
        ->and(array_column(Inventory::recent(10), 'id'))->not->toContain(20, 25, 26, 27)
        ->and(array_column(Inventory::childrenOf(6), 'name'))->toBe(['Camping tent', 'Tool chest'])
        ->and(array_column(Inventory::childrenOf(null), 'name'))->not->toContain('Attic', 'Loose lamp')
        ->and(Inventory::descendantIdsOf(6))->not->toContain(20, 22, 25);

    $inventory = Native::visit('/inventory')
        ->assertDontSee('Attic')
        ->assertDontSee('Loose lamp')
        ->assertSee('2 things inside');
    expect(array_column($inventory->get('places'), 'name'))->toBe(['Basement', 'Garage', 'Kitchen']);
    $inventory->input('updateSearch', 'lamp')->assertSee('Nothing called “lamp”');

    Native::visit('/inventory/6')
        ->assertSee('What’s inside (2)')
        ->assertDontSee('Old lamp')
        ->assertDontSee('Bike pump')
        ->tap('item-move')
        ->assertDontSee('Attic');

    foreach (['/inventory/20', '/inventory/20/edit', '/inventory/20/qr-code'] as $path) {
        Native::visit($path)->assertDontSee('Old lamp');
    }

    Native::visit('/inventory/create')->tap('create-item-parent')->assertDontSee('Attic');
    Native::visit('/i/20')->assertScreen(OpenItemLink::class);

    expect(collect(Native::visit('/dashboard')->get('recentItems'))->pluck('name')->all())->not->toContain('Lamp shade', 'Attic', 'Loose lamp', 'Old lamp');
});
