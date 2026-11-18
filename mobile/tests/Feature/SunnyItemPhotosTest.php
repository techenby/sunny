<?php

use App\Http\Integrations\Sunny\Requests\DeleteRecordRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\PendingWrite;
use Illuminate\Http\UploadedFile;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

function sunnyPhoto(int $id, int $itemId, int $position, ?string $clientUuid = null): array
{
    return [
        'id' => $id, 'item_id' => $itemId, 'position' => $position, 'client_uuid' => $clientUuid,
        'url' => "https://sunny.example/photos/{$id}.jpg", 'thumb_url' => null,
        'created_at' => '2026-11-16T10:00:00Z', 'updated_at' => '2026-11-16T10:00:00Z',
    ];
}

function sunnyItemWithPhotos(int $id, array $photos): array
{
    return [...Item::find($id)->toArray(), 'photos' => $photos];
}

function fakeSunnyPhotoServer(int $itemId, array &$sent): void
{
    $nextId = 600;

    Saloon::fake([
        SaveRecordRequest::class => function (PendingRequest $pending) use ($itemId, &$sent, &$nextId): MockResponse {
            $endpoint = $pending->getRequest()->resolveEndpoint();
            $body = $pending->body()->all();
            $sent[] = $pending->getMethod()->value.' '.$endpoint;

            if (str_ends_with($endpoint, '/photos')) {
                return MockResponse::make(['data' => sunnyPhoto($nextId++, $itemId, 0, is_array($body) ? null : ($body['client_uuid']->value ?? null))], 201);
            }

            $order = is_array($body) ? ($body['photo_order'] ?? []) : [];

            return MockResponse::make(['data' => sunnyItemWithPhotos($itemId, array_map(fn (int $id, int $position): array => sunnyPhoto($id, $itemId, $position), $order, array_keys($order)))]);
        },
        DeleteRecordRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = 'DELETE '.$pending->getRequest()->resolveEndpoint();

            return MockResponse::make([], 204);
        },
    ]);
}

it('keeps the photos Sunny sends with each item, in order', function (): void {
    app(SunnyStore::class)->saveRecord('items', sunnyItemWithPhotos(3, [sunnyPhoto(502, 3, 1), sunnyPhoto(501, 3, 0)]));

    expect(Item::find(3)->photos->pluck('id')->all())->toBe([501, 502])
        ->and(ItemPhoto::find(501)->url)->toBe('https://sunny.example/photos/501.jpg');

    app(SunnyStore::class)->saveRecord('items', sunnyItemWithPhotos(3, [sunnyPhoto(502, 3, 0)]));

    expect(Item::find(3)->photos->pluck('id')->all())->toBe([502]);
});

it('forgets photos whose item is gone after a download', function (): void {
    app(SunnyStore::class)->saveRecord('items', sunnyItemWithPhotos(3, [sunnyPhoto(501, 3, 0)]));

    $data = json_decode(file_get_contents(__DIR__.'/../Fixtures/sunny.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach (['recipes', 'items', 'checklists', 'routines'] as $type) {
        $data[$type] = array_map(fn (array $record): array => $record + ['team_id' => 1], $data[$type]);
    }
    $data['items'] = array_values(array_filter($data['items'], fn (array $item): bool => $item['id'] !== 3));
    app(SunnyStore::class)->applySnapshot([...$data, 'routine_occurrences' => [], 'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']], 'synced_at' => now()->toIso8601String()]);

    expect(ItemPhoto::query()->whereKey(501)->exists())->toBeFalse();
});

it('shows every photo on the item screen and switches between them', function (): void {
    app(SunnyStore::class)->saveRecord('items', sunnyItemWithPhotos(3, [sunnyPhoto(501, 3, 0), sunnyPhoto(502, 3, 1)]));

    Native::visit('/inventory/3')
        ->assertElement('image', fn (array $node): bool => ($node['ref'] ?? null) === 'item-photo'
            && ($node['props']['src'] ?? null) === 'https://sunny.example/photos/501.jpg')
        ->assertSee('1 / 2')
        ->assertSee('Main photo')
        ->assertDontSee('Add a photo')
        ->tap('item-photo-thumb-1')
        ->assertSee('2 / 2')
        ->assertDontSee('Main photo')
        ->assertElement('image', fn (array $node): bool => ($node['ref'] ?? null) === 'item-photo'
            && ($node['props']['src'] ?? null) === 'https://sunny.example/photos/502.jpg');
});

it('offers to add a photo when an item has none', function (): void {
    Native::visit('/inventory/3')
        ->assertMissingElement('image', fn (array $node): bool => ($node['ref'] ?? null) === 'item-photo')
        ->assertSee('Add a photo');
});

it('creates an item with its photos and uploads them once Sunny has the item', function (): void {
    $files = [UploadedFile::fake()->image('front.jpg'), UploadedFile::fake()->image('back.jpg')];
    [$front, $back] = array_map(fn (UploadedFile $file): string => $file->getPathname(), $files);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 500)]);
    $screen = Native::visit('/inventory/create')
        ->set('name', 'Tent')
        ->emitNative(PhotoTaken::class, ['path' => $front])
        ->emitNative(PhotoTaken::class, ['path' => $back]);

    $screen->tap('create-item-submit')->assertSet('error', '');

    $item = Item::query()->where('name', 'Tent')->sole();
    $photos = ItemPhoto::query()->where('item_id', $item->id)->orderBy('position')->get();

    expect($item->id)->toBeLessThan(0)
        ->and($photos)->toHaveCount(2)
        ->and($photos->pluck('position')->all())->toBe([0, 1])
        ->and($item->photo_url)->toBe($photos->first()->url)
        ->and(PendingWrite::query()->orderBy('id')->pluck('resource')->all())->toBe(['items', 'item_photos', 'item_photos'])
        ->and(PendingWrite::query()->where('resource', 'items')->sole()->payload)->not->toHaveKey('photo_order');

    $sent = [];
    Saloon::fake([
        SaveRecordRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $endpoint = $pending->getRequest()->resolveEndpoint();
            $sent[] = $endpoint;

            return str_ends_with($endpoint, '/photos')
                ? MockResponse::make(['data' => sunnyPhoto(600 + count($sent), 90, count($sent) - 2, $pending->body()->get('client_uuid')->value)], 201)
                : MockResponse::make(['data' => [...Item::find(3)->toArray(), 'id' => 90, 'name' => 'Tent', 'parent_id' => null]], 201);
        },
    ]);
    app(SunnyWrites::class)->push();

    expect($sent)->toBe(['/teams/family/items', '/teams/family/items/90/photos', '/teams/family/items/90/photos'])
        ->and(PendingWrite::count())->toBe(0)
        ->and(ItemPhoto::query()->where('item_id', 90)->orderBy('position')->pluck('id')->all())->toBe([602, 603]);
});

it('removes, adds and reorders photos on an existing item', function (): void {
    app(SunnyStore::class)->saveRecord('items', sunnyItemWithPhotos(3, [sunnyPhoto(501, 3, 0), sunnyPhoto(502, 3, 1), sunnyPhoto(503, 3, 2)]));
    $file = UploadedFile::fake()->image('label.jpg');
    $new = $file->getPathname();
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 500), DeleteRecordRequest::class => MockResponse::make([], 500)]);

    Native::visit('/inventory/3/edit')
        ->tap('edit-item-photo-1-remove')
        ->emitNative(PhotoTaken::class, ['path' => $new])
        ->tap('edit-item-photo-2-primary')
        ->tap('edit-item-submit')
        ->assertSet('error', '')
        ->assertWentBack();

    $newId = ItemPhoto::query()->where('item_id', 3)->where('id', '<', 0)->value('id');

    expect(Item::find(3)->photos->pluck('id')->all())->toBe([$newId, 501, 503])
        ->and(Item::find(3)->photo_url)->toBe(ItemPhoto::find($newId)->url)
        ->and(PendingWrite::query()->where('resource', 'items')->sole()->payload['photo_order'])->toBe([$newId, 501, 503]);

    $sent = [];
    fakeSunnyPhotoServer(3, $sent);
    app(SunnyWrites::class)->push();

    expect($sent)->toContain('DELETE /teams/family/items/3/photos/502')
        ->and(array_values(array_filter($sent, fn (string $line): bool => str_starts_with($line, 'POST') || str_starts_with($line, 'PATCH'))))
        ->toBe(['POST /teams/family/items/3/photos', 'PATCH /teams/family/items/3'])
        ->and(PendingWrite::count())->toBe(0)
        ->and(Item::find(3)->photos->pluck('id')->all())->toBe([600, 501, 503]);
});

it('only sends a new order when it changed', function (): void {
    app(SunnyStore::class)->saveRecord('items', sunnyItemWithPhotos(3, [sunnyPhoto(501, 3, 0), sunnyPhoto(502, 3, 1)]));
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 500), DeleteRecordRequest::class => MockResponse::make([], 500)]);

    Native::visit('/inventory/3/edit')
        ->emitNative(PhotoTaken::class, ['path' => ($file = UploadedFile::fake()->image('extra.jpg'))->getPathname()])
        ->tap('edit-item-photo-0-remove')
        ->tap('edit-item-submit')
        ->assertSet('error', '');

    expect(PendingWrite::query()->where('resource', 'items')->sole()->payload)->not->toHaveKey('photo_order')
        ->and(PendingWrite::query()->where('resource', 'item_photos')->pluck('deletes')->sort()->values()->all())->toBe([false, true]);
});

it('holds back a new order until the photos in it are uploaded, and drops ones Sunny refused', function (): void {
    app(SunnyStore::class)->saveRecord('items', sunnyItemWithPhotos(3, [sunnyPhoto(501, 3, 0)]));
    $outbox = app(SunnyOutbox::class);
    $file = UploadedFile::fake()->image('huge.jpg');
    $refused = $outbox->queue('item_photos', 1, ['item_id' => 3], null, $file->getPathname());
    $outbox->queue('items', 1, ['photo_order' => [$refused, 501]], 3);

    $sent = [];
    Saloon::fake([
        SaveRecordRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $endpoint = $pending->getRequest()->resolveEndpoint();
            $sent[] = $endpoint.' '.json_encode(is_array($pending->body()->all()) ? $pending->body()->all() : []);

            return str_ends_with($endpoint, '/photos')
                ? MockResponse::make(['message' => 'Too big', 'errors' => ['photo' => ['The photo is too large.']]], 422)
                : MockResponse::make(['data' => sunnyItemWithPhotos(3, [sunnyPhoto(501, 3, 0)])]);
        },
    ]);
    app(SunnyWrites::class)->push();

    expect($sent[0])->toStartWith('/teams/family/items/3/photos')
        ->and($sent[1])->toBe('/teams/family/items/3 {"photo_order":[501]}')
        ->and(PendingWrite::query()->where('resource', 'item_photos')->sole()->error)->toBe('The photo is too large.');
});

it('forgets queued photos when their item is deleted, keeping the synced ones for Recently deleted', function (): void {
    app(SunnyStore::class)->saveRecord('items', sunnyItemWithPhotos(3, [sunnyPhoto(501, 3, 0)]));
    $outbox = app(SunnyOutbox::class);
    $file = UploadedFile::fake()->image('front.jpg');
    $outbox->queue('item_photos', 1, ['item_id' => 3], null, $file->getPathname());

    $outbox->delete('items', 1, 3);

    expect(ItemPhoto::query()->where('item_id', 3)->pluck('id')->all())->toBe([501])
        ->and(PendingWrite::query()->pluck('resource')->all())->toBe(['items']);
});
