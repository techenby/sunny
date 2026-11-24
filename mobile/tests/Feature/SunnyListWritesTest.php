<?php

use App\Http\Integrations\Sunny\Requests\ChecklistActionRequest;
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
        'checklists' => $checklists, 'checklist_items' => $items, 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
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

it('sends a new list’s owner and keeps the one Sunny confirms', function (): void {
    Saloon::fake([SaveRecordRequest::class => fn (PendingRequest $request): MockResponse => MockResponse::make(['data' => [
        ...$request->body()->all(), 'team_id' => 1, 'id' => 100, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
    ]], 201)]);

    $list = app(SunnyOutbox::class)->queue('checklists', 1, ['name' => 'Wish list', 'type' => 'wishlist', 'user_id' => 1]);
    expect(Checklist::find($list)->user_id)->toBe(1);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/checklists'
        && $request->body()->get('user_id') === 1);
    expect(Checklist::find(100)->user_id)->toBe(1)->and(PendingWrite::count())->toBe(0);
});

it('checks off an item with a patch that leaves out the list id', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [...ChecklistItem::find(1)->toArray(), 'completed_at' => now()->toIso8601String(), 'completed_by' => 1]])]);

    Native::visit('/lists/1')->tap('list-item-1');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/checklists/1/items/1'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === ['completed' => true]);
    expect(ChecklistItem::find(1)->completed_by)->toBe(1)->and(PendingWrite::count())->toBe(0);
});

it('renames an item with a patch that carries only the new name', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [...ChecklistItem::find(1)->toArray(), 'name' => 'Oat milk']])]);

    Native::visit('/lists/1')->press('editItem(1)')->submit('list-edit-name', 'Oat milk');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/checklists/1/items/1'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === ['name' => 'Oat milk']);
    expect(ChecklistItem::find(1)->name)->toBe('Oat milk')->and(PendingWrite::count())->toBe(0);
});

it('sends a new item’s link to Sunny', function (): void {
    Saloon::fake([SaveRecordRequest::class => fn (PendingRequest $request): MockResponse => MockResponse::make(['data' => [
        ...$request->body()->all(), 'id' => 50, 'checklist_id' => 3, 'position' => 1, 'completed_at' => null,
    ]], 201)]);

    app(SunnyOutbox::class)->queue('checklist_items', 1, ['checklist_id' => 3, 'name' => 'Wool slippers', 'url' => 'https://www.llbean.com/wicked-good-slippers']);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/checklists/3/items'
        && $request->body()->get('url') === 'https://www.llbean.com/wicked-good-slippers');
    expect(ChecklistItem::find(50)->url)->toBe('https://www.llbean.com/wicked-good-slippers');
});

it('removes an item’s link with a patch that clears it', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [...ChecklistItem::find(1)->toArray(), 'url' => null]])]);

    Native::visit('/lists/1')->press('editItem(1)')->tap('list-edit-remove-link');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/checklists/1/items/1'
        && $request->getMethod() === Method::PATCH
        && $request->body()->all() === ['url' => null]);
    expect(ChecklistItem::find(1)->url)->toBeNull()->and(PendingWrite::count())->toBe(0);
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

    expect(PendingWrite::sole()->error)->toBe('The server didn’t confirm this change. Edit it to try again.')
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
        ->toThrow(ValidationException::class, 'This list is no longer available.');
});

it('refuses to change an item from another team', function (): void {
    Team::create(['id' => 2, 'name' => 'Work', 'slug' => 'work', 'server' => SunnyStore::server()]);

    expect(fn () => app(SunnyOutbox::class)->queue('checklist_items', 2, ['completed' => true], 1))
        ->toThrow(ValidationException::class, 'This is no longer available.');
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

it('sends a list’s clear and reset as posts to the list', function (string $method, string $endpoint): void {
    Saloon::fake([ChecklistActionRequest::class => MockResponse::make(['data' => ['id' => 1, 'items' => []]])]);

    app(SunnyOutbox::class)->{$method}(1, 1);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (ChecklistActionRequest $request): bool => $request->resolveEndpoint() === $endpoint && $request->getMethod() === Method::POST);
    expect(PendingWrite::count())->toBe(0);
})->with([
    'clear completed' => ['clearCompleted', '/teams/family/checklists/1/clear-completed'],
    'reset' => ['resetChecklist', '/teams/family/checklists/1/reset'],
]);

it('keeps a clear and a reset to try again when Sunny can’t be reached', function (): void {
    Saloon::fake([ChecklistActionRequest::class => MockResponse::make([], 503)]);
    $outbox = app(SunnyOutbox::class);
    $outbox->resetChecklist(1, 1);
    $outbox->clearCompleted(1, 1);

    expect(fn () => app(SunnyWrites::class)->push())->toThrow(RequestException::class);

    $outbox->resetChecklist(1, 1);

    expect(PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'payload', 'error')->all())->toBe([
        ['resource' => 'checklist_clears', 'record_id' => 1, 'payload' => [], 'error' => null],
        ['resource' => 'checklist_resets', 'record_id' => 1, 'payload' => [], 'error' => null],
    ]);
});

it('drops a clear Sunny refuses for good', function (int $status): void {
    Saloon::fake([ChecklistActionRequest::class => MockResponse::make([], $status)]);

    app(SunnyOutbox::class)->clearCompleted(1, 1);
    app(SunnyWrites::class)->push();

    expect(PendingWrite::count())->toBe(0);
})->with([403, 404]);

it('clears ticked items on the phone, sending ones ticked offline as deletions', function (): void {
    Saloon::fake([
        DeleteRecordRequest::class => MockResponse::make([], 204),
        ChecklistActionRequest::class => MockResponse::make(['data' => ['id' => 1, 'items' => []]]),
    ]);
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('checklist_items', 1, ['completed' => true], 1);
    $new = $outbox->queue('checklist_items', 1, ['checklist_id' => 1, 'name' => 'Coffee']);
    $outbox->queue('checklist_items', 1, ['completed' => true], $new);

    $outbox->clearCompleted(1, 1);

    expect(ChecklistItem::where('checklist_id', 1)->pluck('name')->all())->toBe(['Bread'])
        ->and(PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'deletes')->all())->toBe([
            ['resource' => 'checklist_items', 'record_id' => 1, 'deletes' => true],
            ['resource' => 'checklist_clears', 'record_id' => 1, 'deletes' => false],
        ]);

    app(SunnyWrites::class)->push();

    Saloon::assertSentCount(2);
    expect(PendingWrite::count())->toBe(0);
});

it('unchecks every item on the phone, folding ticks still waiting into the reset', function (): void {
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('checklist_items', 1, ['completed' => true], 1);
    $outbox->queue('checklist_items', 1, ['name' => 'Sourdough'], 3);

    $outbox->resetChecklist(1, 1);

    expect(ChecklistItem::where('checklist_id', 1)->whereNotNull('completed_at')->count())->toBe(0)
        ->and(ChecklistItem::find(2)->completed_by)->toBeNull()
        ->and(PendingWrite::orderBy('id')->get()->map->only('resource', 'record_id', 'payload')->all())->toBe([
            ['resource' => 'checklist_items', 'record_id' => 1, 'payload' => ['completed' => false, 'checklist_id' => 1]],
            ['resource' => 'checklist_items', 'record_id' => 3, 'payload' => ['name' => 'Sourdough', 'checklist_id' => 1]],
            ['resource' => 'checklist_resets', 'record_id' => 1, 'payload' => []],
        ]);
});

it('clears and resets a list that never reached Sunny without sending anything', function (): void {
    Saloon::fake([]);
    $outbox = app(SunnyOutbox::class);
    $list = $outbox->queue('checklists', 1, ['name' => 'Packing', 'type' => 'todo']);
    $socks = $outbox->queue('checklist_items', 1, ['checklist_id' => $list, 'name' => 'Socks']);
    $hat = $outbox->queue('checklist_items', 1, ['checklist_id' => $list, 'name' => 'Hat']);
    $outbox->queue('checklist_items', 1, ['completed' => true], $socks);
    $outbox->queue('checklist_items', 1, ['completed' => true], $hat);

    $outbox->resetChecklist(1, $list);
    $outbox->queue('checklist_items', 1, ['completed' => true], $hat);
    $outbox->clearCompleted(1, $list);

    expect(ChecklistItem::where('checklist_id', $list)->pluck('name')->all())->toBe(['Socks'])
        ->and(PendingWrite::where('resource', 'checklist_items')->sole()->payload)->toBe(['checklist_id' => $list, 'name' => 'Socks', 'completed' => false])
        ->and(PendingWrite::whereIn('resource', ['checklist_clears', 'checklist_resets'])->count())->toBe(0);
});

it('drops a waiting clear when its list is deleted', function (): void {
    $outbox = app(SunnyOutbox::class);
    $outbox->clearCompleted(1, 1);
    $outbox->delete('checklists', 1, 1);

    expect(PendingWrite::sole()->only('resource', 'deletes'))->toBe(['resource' => 'checklists', 'deletes' => true]);
});
