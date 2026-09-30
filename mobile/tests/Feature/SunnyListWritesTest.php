<?php

use App\Http\Integrations\Sunny\Requests\DeleteRecordRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\PendingWrite;
use App\Models\Team;
use Illuminate\Validation\ValidationException;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

function listSnapshot(array $checklists, array $items): array
{
    return [
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'recipes' => [], 'items' => [],
        'checklists' => $checklists, 'checklist_items' => $items,
        'synced_at' => now()->toIso8601String(),
    ];
}

it('sends a new list, then the items added to it once it has an id', function (): void {
    $nextId = 100;
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request) use (&$nextId): MockResponse {
        $body = $request->body()->all();
        $owner = str_contains($request->getUrl(), '/items') ? ['checklist_id' => 100, 'position' => 1, 'completed_at' => null] : ['team_id' => 1];

        return MockResponse::make(['data' => [...$body, ...$owner, 'id' => $nextId++, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]], 201);
    }]);
    $outbox = app(SunnyOutbox::class);
    $list = $outbox->queue('checklists', 1, ['name' => 'Hardware store', 'type' => 'shopping']);
    $outbox->queue('checklist_items', 1, ['checklist_id' => $list, 'name' => 'Nails']);

    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/checklists'
        && $request->body()->get('client_uuid') !== null);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/checklists/100/items'
        && $request->getMethod() === Method::POST
        && $request->body()->all() === ['name' => 'Nails', 'client_uuid' => $request->body()->get('client_uuid')]);
    expect(Checklist::find(100)->local_id)->toBe($list)
        ->and(ChecklistItem::find(101)->checklist_id)->toBe(100)
        ->and(PendingWrite::count())->toBe(0);
    Native::visit('/lists/'.$list)->assertSee('Nails');
});

it('checks off an item with a patch that leaves out the list id', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [...ChecklistItem::find(1)->toArray(), 'completed_at' => now()->toIso8601String(), 'completed_by' => 1]])]);

    Native::visit('/lists/1')->tap('list-item-1');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/checklists/1/items/1'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === ['completed' => true]);
    expect(ChecklistItem::find(1)->completed_by)->toBe(1)->and(PendingWrite::count())->toBe(0);
});

it('creates an item already checked off when it was ticked before reaching Sunny', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 503)]);
    $outbox = app(SunnyOutbox::class);
    $id = $outbox->queue('checklist_items', 1, ['checklist_id' => 1, 'name' => 'Coffee']);
    $outbox->queue('checklist_items', 1, ['completed' => true], $id);

    expect(PendingWrite::sole()->payload)->toBe(['checklist_id' => 1, 'name' => 'Coffee', 'completed' => true])
        ->and(ChecklistItem::find($id)->completed_at)->not->toBeNull();
});

it('flags a saved item Sunny says belongs to another list', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [...ChecklistItem::find(1)->toArray(), 'checklist_id' => 2]])]);

    Native::visit('/lists/1')->tap('list-item-1');

    expect(PendingWrite::sole()->error)->toBe('Sunny didn’t confirm this change. Edit it to try again.')
        ->and(ChecklistItem::find(1)->checklist_id)->toBe(1);
});

it('sends deletions for lists and items', function (string $resource, int $id, string $endpoint): void {
    Saloon::fake([DeleteRecordRequest::class => MockResponse::make([], 204)]);

    app(SunnyOutbox::class)->delete($resource, 1, $id);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (DeleteRecordRequest $request): bool => $request->resolveEndpoint() === $endpoint && $request->getMethod() === Method::DELETE);
    expect(PendingWrite::count())->toBe(0);
})->with([
    'list' => ['checklists', 1, '/teams/family/checklists/1'],
    'item' => ['checklist_items', 3, '/teams/family/checklists/1/items/3'],
]);

it('drops a deletion Sunny refuses for good', function (int $status): void {
    Saloon::fake([DeleteRecordRequest::class => MockResponse::make([], $status)]);

    app(SunnyOutbox::class)->delete('checklist_items', 1, 3);
    app(SunnyWrites::class)->push();

    expect(PendingWrite::count())->toBe(0);
})->with([403, 404]);

it('keeps a deletion to try again when Sunny can’t be reached', function (): void {
    Saloon::fake([DeleteRecordRequest::class => MockResponse::make([], 503)]);

    app(SunnyOutbox::class)->delete('checklist_items', 1, 3);

    expect(fn () => app(SunnyWrites::class)->push())->toThrow(RequestException::class);
    expect(PendingWrite::sole()->only('deletes', 'error'))->toBe(['deletes' => true, 'error' => null]);
});

it('replaces an item’s waiting change with its deletion', function (): void {
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('checklist_items', 1, ['completed' => true], 1);
    $outbox->delete('checklist_items', 1, 1);

    expect(PendingWrite::sole()->only('record_id', 'deletes', 'payload', 'version'))
        ->toBe(['record_id' => 1, 'deletes' => true, 'payload' => ['checklist_id' => 1], 'version' => 2]);
});

it('drops changes waiting for a list’s items when the list is deleted', function (): void {
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('checklist_items', 1, ['completed' => true], 1);
    $outbox->queue('checklist_items', 1, ['checklist_id' => 1, 'name' => 'Coffee']);
    $outbox->delete('checklist_items', 1, 3);

    $outbox->delete('checklists', 1, 1);

    expect(PendingWrite::sole()->only('resource', 'record_id'))->toBe(['resource' => 'checklists', 'record_id' => 1])
        ->and(ChecklistItem::where('checklist_id', 1)->count())->toBe(0);
});

it('forgets a list that never reached Sunny, along with its items', function (): void {
    Saloon::fake([]);
    $outbox = app(SunnyOutbox::class);
    $list = $outbox->queue('checklists', 1, ['name' => 'Hardware store', 'type' => 'shopping']);
    $outbox->queue('checklist_items', 1, ['checklist_id' => $list, 'name' => 'Nails']);

    $outbox->delete('checklists', 1, $list);
    app(SunnyWrites::class)->push();

    Saloon::assertNothingSent();
    expect(Checklist::find($list))->toBeNull()
        ->and(ChecklistItem::where('checklist_id', $list)->count())->toBe(0)
        ->and(PendingWrite::count())->toBe(0);
});

it('refuses to add an item to a list that is no longer on the phone', function (): void {
    expect(fn () => app(SunnyOutbox::class)->queue('checklist_items', 1, ['checklist_id' => 99, 'name' => 'Nails']))
        ->toThrow(ValidationException::class, 'This list is no longer available. Sync with Sunny.');
});

it('refuses to change an item from another team', function (): void {
    Team::create(['id' => 2, 'name' => 'Work', 'slug' => 'work', 'server' => SunnyStore::server()]);

    expect(fn () => app(SunnyOutbox::class)->queue('checklist_items', 2, ['completed' => true], 1))
        ->toThrow(ValidationException::class, 'This record is no longer available. Sync with Sunny.');
});

it('keeps a list deleted on the phone out of a download that still has it', function (): void {
    app(SunnyOutbox::class)->delete('checklists', 1, 1);
    Saloon::fake([SyncRequest::class => MockResponse::make(listSnapshot(
        [[...Checklist::find(2)->toArray(), 'type' => 'todo'], ['id' => 1, 'team_id' => 1, 'user_id' => null, 'type' => 'shopping', 'name' => 'Groceries', 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]],
        [['id' => 1, 'checklist_id' => 1, 'name' => 'Milk', 'position' => 1, 'completed_at' => null, 'completed_by' => null, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]],
    ))]);

    app(SunnySync::class)->sync();

    expect(Checklist::pluck('id')->all())->toBe([2])->and(ChecklistItem::count())->toBe(0);
});

it('downloads lists and items, dropping any Sunny no longer has', function (): void {
    $time = now()->toIso8601String();
    Saloon::fake([SyncRequest::class => MockResponse::make(listSnapshot(
        [
            ['id' => 1, 'team_id' => 1, 'user_id' => null, 'type' => 'shopping', 'name' => 'Groceries', 'created_at' => $time, 'updated_at' => $time],
            ['id' => 2, 'team_id' => 1, 'user_id' => null, 'type' => 'todo', 'name' => 'Chores', 'created_at' => $time, 'updated_at' => $time, 'deleted_at' => $time],
        ],
        [
            ['id' => 1, 'checklist_id' => 1, 'name' => 'Oat milk', 'position' => 1, 'completed_at' => $time, 'completed_by' => 1, 'created_at' => $time, 'updated_at' => $time],
            ['id' => 4, 'checklist_id' => 2, 'name' => 'Vacuum the stairs', 'position' => 1, 'completed_at' => null, 'completed_by' => null, 'created_at' => $time, 'updated_at' => $time],
        ],
    ))]);

    app(SunnySync::class)->sync();

    expect(Checklist::pluck('name', 'id')->all())->toBe([1 => 'Groceries'])
        ->and(ChecklistItem::pluck('name', 'id')->all())->toBe([1 => 'Oat milk'])
        ->and(ChecklistItem::find(1)->completed_by)->toBe(1);
});

it('keeps items still waiting for Sunny when a download lands first', function (): void {
    $outbox = app(SunnyOutbox::class);
    $list = $outbox->queue('checklists', 1, ['name' => 'Hardware store', 'type' => 'shopping']);
    $nails = $outbox->queue('checklist_items', 1, ['checklist_id' => $list, 'name' => 'Nails']);
    $outbox->queue('checklist_items', 1, ['completed' => true], 1);
    Saloon::fake([SyncRequest::class => MockResponse::make(listSnapshot([], []))]);

    app(SunnySync::class)->sync();

    expect(Checklist::pluck('id')->all())->toBe([$list])
        ->and(ChecklistItem::pluck('id')->all())->toBe([$nails])
        ->and(PendingWrite::where('record_id', 1)->exists())->toBeFalse();
});

it('rejects a download with an unknown list type', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make(listSnapshot(
        [['id' => 1, 'team_id' => 1, 'user_id' => null, 'type' => 'groceries', 'name' => 'Groceries', 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String()]],
        [],
    ))]);

    expect(fn () => app(SunnySync::class)->sync())->toThrow(ValidationException::class);
    expect(Checklist::count())->toBe(3);
});

it('keeps lists with changes waiting when local data is cleared', function (): void {
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('checklist_items', 1, ['completed' => true], 4);
    $list = $outbox->queue('checklists', 1, ['name' => 'Hardware store', 'type' => 'shopping']);

    app(SunnyStore::class)->clear();

    expect(Checklist::orderBy('id')->pluck('id')->all())->toBe([$list, 2])
        ->and(ChecklistItem::pluck('id')->all())->toBe([4]);
});
