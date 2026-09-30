<?php

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\LogoutRequest;
use App\Http\Integrations\Sunny\Requests\RefreshTokenRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\Recipe;
use App\Models\Team;
use App\NativeComponents\Inventory;
use App\NativeComponents\Recipes;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\ValidationException;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token'])
        ->respondTo('SecureStorage.Set', ['success' => true])
        ->respondTo('SecureStorage.Delete', ['success' => true]);
    $this->snapshot = [
        'teams' => [['id' => 7, 'name' => 'Family']],
        'recipes' => [[
            'id' => 42, 'team_id' => 7, 'name' => 'Soup', 'tags' => ['dinner'],
            'created_at' => '2026-09-01T12:00:00+00:00', 'updated_at' => '2026-09-23T12:00:00+00:00',
        ]],
        'items' => [
            ['id' => 10, 'team_id' => 7, 'name' => 'Kitchen', 'type' => 'location', 'created_at' => '2026-09-01T12:00:00+00:00', 'updated_at' => '2026-09-23T12:00:00+00:00'],
            ['id' => 11, 'team_id' => 7, 'parent_id' => 10, 'name' => 'Flour', 'type' => 'item', 'metadata' => ['quantity' => '2'], 'created_at' => '2026-09-01T12:00:00+00:00', 'updated_at' => '2026-09-23T12:00:00+00:00'],
        ],
        'synced_at' => '2026-09-23T13:00:00+00:00',
    ];
});

it('downloads into dedicated tables and serves lists and details without further API requests', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();

    expect(Recipe::find(42)->team->name)->toBe('Family')
        ->and(Recipe::find(42)->tags)->toBe(['dinner'])
        ->and(Item::find(11)->parent->name)->toBe('Kitchen')
        ->and(Item::find(11)->type)->toBe(ItemType::Item)
        ->and(Item::find(11)->metadata)->toBe(['quantity' => '2'])
        ->and(app(SunnyStore::class)->lastSyncedAt())->toBe($this->snapshot['synced_at']);

    Native::visit('/recipes')->assertSee('Soup');
    Native::visit('/recipes/42')->assertSee('Soup');
    Native::visit('/inventory')->assertSee('Kitchen');
    Native::visit('/inventory/10')->assertSee('Flour');
    Native::visit('/dashboard')->assertSee('Soup')->assertSee('Flour');
    Saloon::assertSentCount(1);
});

it('updates rows idempotently and removes deleted or absent records', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();
    app(SunnySync::class)->sync();
    expect(Recipe::count())->toBe(1)->and(Item::count())->toBe(2)
        ->and(Recipe::find(42)->updated_at->toIso8601String())->toBe('2026-09-23T12:00:00+00:00');

    $this->snapshot['recipes'][0]['name'] = 'Updated soup';
    $this->snapshot['recipes'][0]['tags'] = null;
    $this->snapshot['items'][1]['deleted_at'] = '2026-09-23T12:30:00+00:00';
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();
    expect(Recipe::find(42)->name)->toBe('Updated soup')->and(Recipe::find(42)->tags)->toBeNull()
        ->and(Item::find(11))->toBeNull();

    $this->snapshot['recipes'] = [];
    $this->snapshot['items'] = [];
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();
    expect(Recipe::count())->toBe(0)->and(Item::count())->toBe(0);
});

it('clears local fields the API leaves out of a later snapshot', function (): void {
    $this->snapshot['recipes'][0]['notes'] = 'Simmer longer';
    $this->snapshot['items'][0]['metadata'] = ['brand' => 'Acme'];
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();
    expect(Recipe::find(42)->notes)->toBe('Simmer longer')->and(Item::find(10)->metadata)->toBe(['brand' => 'Acme']);

    unset($this->snapshot['recipes'][0]['notes'], $this->snapshot['items'][0]['metadata']);
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();
    expect(Recipe::find(42)->notes)->toBeNull()->and(Item::find(10)->metadata)->toBeNull();
});

it('removes records for teams that were deleted or are no longer accessible', function (bool $deleted): void {
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();
    $this->snapshot['teams'] = $deleted ? [['id' => 7, 'name' => 'Family', 'deleted_at' => '2026-09-23T12:30:00+00:00']] : [];
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();
    expect(Team::count())->toBe(0)->and(Recipe::count())->toBe(0)->and(Item::count())->toBe(0);
})->with([true, false]);

it('keeps local data and sync state when the API fails or returns invalid data', function (bool $invalid): void {
    seedSunnyData();
    $lastSynced = app(SunnyStore::class)->lastSyncedAt();
    Saloon::fake([SyncRequest::class => MockResponse::make(['recipes' => []], $invalid ? 200 : 500)]);
    expect(fn () => app(SunnySync::class)->sync())->toThrow($invalid ? ValidationException::class : RequestException::class);
    expect(Recipe::count())->toBe(6)->and(Item::count())->toBe(15)
        ->and(app(SunnyStore::class)->lastSyncedAt())->toBe($lastSynced);
})->with([true, false]);

it('rolls back the whole snapshot if a database write fails', function (): void {
    seedSunnyData();
    $lastSynced = app(SunnyStore::class)->lastSyncedAt();
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    Item::creating(function (): void {
        throw new RuntimeException('Simulated disk failure');
    });

    try {
        expect(fn () => app(SunnySync::class)->sync())->toThrow(RuntimeException::class, 'Simulated disk failure');
        expect(Recipe::find(1)->name)->toBe('Buttermilk Pancakes')
            ->and(Recipe::find(42))->toBeNull()->and(Item::count())->toBe(15)
            ->and(app(SunnyStore::class)->lastSyncedAt())->toBe($lastSynced);
    } finally {
        Item::flushEventListeners();
    }
});

it('downloads on first dashboard entry and permits manual refresh without fetching on every visit', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    $screen = Native::visit('/dashboard')->assertSee('Soup');
    Native::visit('/dashboard')->assertSee('Soup');
    Saloon::assertSentCount(1);
    $screen->call('sync')->assertSee('Soup');
    Saloon::assertSentCount(2);
    $this->travel(6)->minutes();
    Native::visit('/dashboard')->assertSee('Soup');
    Saloon::assertSentCount(3);
});

it('opens downloaded data offline and allows retrying failed sync', function (): void {
    seedSunnyData();
    $this->travel(6)->minutes();
    Saloon::fake([
        GetUserRequest::class => MockResponse::make([], 503),
        SyncRequest::class => MockResponse::make([], 503),
    ]);
    Native::visit('/')->assertReplacedWith('/dashboard');
    $screen = Native::visit('/dashboard')->assertSee('Buttermilk Pancakes')
        ->emitNative('sunny-sync-complete', ['status' => 'failed', 'exceptionClass' => RequestException::class])
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === 'Unable to sync. Your previously downloaded data is still available.')
        ->assertSet('syncError', '');
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    $screen->call('sync')->assertSee('Soup');
});

it('shows a retry row when the first download fails', function (): void {
    Saloon::fake([SyncRequest::class => MockResponse::make([], 503)]);
    $screen = Native::visit('/dashboard')
        ->emitNative('sunny-sync-complete', ['status' => 'failed', 'exceptionClass' => RequestException::class])
        ->assertSee('Unable to download your data. Check your connection and tap here to retry.');
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    $screen->tap('sync-error')->assertSee('Soup')->assertSet('syncError', '')->assertDontSee('Download failed');
});

it('syncs in the background when returning to a stale dashboard', function (): void {
    $async = AsyncTask::fake();
    seedSunnyData();
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    $recipe = Native::visit('/dashboard')->assertDontSee('Soup')->tap('dashboard-recipes-1')->follow();
    $async->assertNotDispatched();
    $this->travel(6)->minutes();
    $recipe->goBack()->assertSee('Soup');
    $async->assertDispatchedTimes(1);
    $async->assertShared('sunny-sync-complete');
});

it('refreshes the active recipes screen when a shared sync completes', function (): void {
    seedSunnyData();
    $screen = Native::visit('/recipes')->assertSee('Buttermilk Pancakes');
    Recipe::find(1)->update(['name' => 'Blueberry Pancakes']);

    $screen->emitNative('sunny-sync-complete', ['status' => 'finished']);

    $screen->assertSee('Blueberry Pancakes')->assertDontSee('Buttermilk Pancakes');
});

it('starts a shared sync while the recipes screen stays open', function (): void {
    seedSunnyData();
    $async = AsyncTask::fake();
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    $screen = Native::visit('/recipes')->assertSee('Buttermilk Pancakes');

    $this->travel(6)->minutes();
    $screen->firePoll('pollSunnySync');

    $async->assertShared('sunny-sync-complete');
    expect(Recipe::find(42)->name)->toBe('Soup');
});

it('backs off after a failed background sync instead of retrying on every poll', function (): void {
    seedSunnyData();
    $async = AsyncTask::fake();
    Saloon::fake([SyncRequest::class => MockResponse::make([], 503)]);
    $this->travel(6)->minutes();
    $screen = Native::visit('/dashboard');
    $screen->firePoll('pollSunnySync');
    Native::visit('/recipes')->firePoll('pollSunnySync');
    $async->assertDispatchedTimes(1);

    $this->travel(6)->minutes();
    $screen->firePoll('pollSunnySync');
    $async->assertDispatchedTimes(2);
});

it('does not start a background sync while another is in flight', function (): void {
    seedSunnyData();
    $async = AsyncTask::fake();
    $this->travel(6)->minutes();
    Cache::add('sunny-sync-dispatched', true, 300);

    Native::visit('/dashboard')->firePoll('pollSunnySync');

    $async->assertNotDispatched();
});

it('only runs a scheduled sync when local data is stale', function (): void {
    seedSunnyData();
    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);

    $this->artisan('sunny:sync')->assertSuccessful();
    Saloon::assertSentCount(0);

    $this->travel(6)->minutes();
    $this->artisan('sunny:sync')->assertSuccessful();
    expect(Recipe::find(42)->name)->toBe('Soup');
    Saloon::assertSentCount(1);
});

it('runs a scheduled sync while changes are waiting even when local data is fresh', function (): void {
    seedSunnyData();
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake([
        SaveRecordRequest::class => MockResponse::make(['data' => [...Recipe::find(1)->toArray(), 'name' => 'Soup']]),
        SyncRequest::class => MockResponse::make($this->snapshot),
    ]);

    $this->artisan('sunny:sync')->assertSuccessful();

    Saloon::assertSent(SaveRecordRequest::class);
    Saloon::assertSent(SyncRequest::class);
    expect(PendingWrite::count())->toBe(0);
});

it('starts a background sync on the next poll while changes are waiting', function (): void {
    seedSunnyData();
    $async = AsyncTask::fake();
    $screen = Native::visit('/recipes');
    $screen->firePoll('pollSunnySync');
    $async->assertNotDispatched();

    PendingWrite::create(['server' => SunnyStore::server(), 'resource' => 'recipes', 'record_id' => 1, 'team_id' => 1, 'payload' => ['name' => 'Soup'], 'error' => 'Refused']);
    $screen->firePoll('pollSunnySync');
    $async->assertNotDispatched();

    PendingWrite::query()->update(['error' => null]);
    $screen->firePoll('pollSunnySync');
    $async->assertDispatchedTimes(1);
});

it('returns to login when a background sync finds the session revoked', function (): void {
    $bridge = Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token'])->respondTo('SecureStorage.Delete', ['success' => true]);
    seedSunnyData();
    $this->travel(6)->minutes();
    Saloon::fake([SyncRequest::class => MockResponse::make([], 401)]);
    Native::visit('/dashboard')
        ->emitNative('sunny-sync-complete', ['status' => 'failed', 'exceptionClass' => UnauthorizedException::class])
        ->assertReplacedWith('/login');
    expect(Recipe::count())->toBe(0);
    $bridge->assertCalled('SecureStorage.Delete');
});

it('starts a fresh background sync after signing back in from a revoked session', function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token'])->respondTo('SecureStorage.Delete', ['success' => true]);
    seedSunnyData();
    $async = AsyncTask::fake();
    $this->travel(6)->minutes();
    Cache::add('sunny-sync-dispatched', true, 300);
    Native::visit('/dashboard')
        ->emitNative('sunny-sync-complete', ['status' => 'failed', 'exceptionClass' => UnauthorizedException::class])
        ->assertReplacedWith('/login');

    Native::visit('/dashboard');

    $async->assertDispatchedTimes(1);
});

it('clears local data when a session is revoked', function (): void {
    seedSunnyData();
    Saloon::fake([SyncRequest::class => MockResponse::make([], 401)]);
    Native::visit('/dashboard')->call('sync')->assertReplacedWith('/login');
    expect(Recipe::count())->toBe(0)->and(Item::count())->toBe(0)->and(app(SunnyStore::class)->lastSyncedAt())->toBeNull();
});

it('lets a new login start a background sync after an earlier sync failed', function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'new-token'])->respondTo('SecureStorage.Set', ['success' => true]);
    $async = AsyncTask::fake();
    Cache::add('sunny-sync-dispatched', true, 300);
    Saloon::fake([CreateTokenRequest::class => MockResponse::make(['token' => 'new-token'])]);

    app(SunnyAuth::class)->login('person@example.com', 'password', 'phone');
    Native::visit('/dashboard');

    $async->assertDispatchedTimes(1);
});

it('clears the previous account data on login or logout but keeps it on token refresh', function (string $action): void {
    seedSunnyData();
    app(SunnyStore::class)->rememberAccount(5);
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake([
        CreateTokenRequest::class => MockResponse::make(['id' => 5, 'token' => 'new-token']),
        LogoutRequest::class => MockResponse::make([], 204),
        RefreshTokenRequest::class => MockResponse::make(['token' => 'refreshed-token']),
    ]);
    $auth = app(SunnyAuth::class);
    match ($action) {
        'login' => $auth->login('person@example.com', 'password', 'phone'),
        'logout' => $auth->logout(),
        'refresh' => $auth->refresh(),
    };
    expect(Recipe::pluck('name')->all())->toBe($action === 'refresh' ? Recipe::pluck('name')->all() : ['Soup'])
        ->and(Recipe::count())->toBe($action === 'refresh' ? 6 : 1)
        ->and(Item::count())->toBe($action === 'refresh' ? 15 : 0)
        ->and(Team::pluck('id')->all())->toBe([1])
        ->and(PendingWrite::count())->toBe(1);
})->with(['login', 'logout', 'refresh']);

it('sends changes kept through signing out once the same account signs back in', function (): void {
    seedSunnyData();
    $photo = UploadedFile::fake()->image('photo.jpg');
    Saloon::fake([GetUserRequest::class => MockResponse::make(['data' => ['id' => 5]])]);
    Native::visit('/')->assertReplacedWith('/dashboard');
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1, $photo->getPathname());
    Saloon::fake([LogoutRequest::class => MockResponse::make([], 204), CreateTokenRequest::class => MockResponse::make(['id' => 5, 'token' => 'new-token'])]);
    app(SunnyAuth::class)->logout();
    app(SunnyAuth::class)->login('person@example.com', 'password', 'phone');
    expect(PendingWrite::sole()->photo_path)->toBeFile();

    $saved = [...Recipe::find(1)->toArray(), 'name' => 'Soup', 'photo_url' => 'https://sunny.example/soup.jpg'];
    Saloon::fake([
        SaveRecordRequest::class => MockResponse::make(['data' => $saved]),
        SyncRequest::class => MockResponse::make(['teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']], 'recipes' => [$saved], 'items' => [], 'synced_at' => now()->toIso8601String()]),
    ]);
    app(SunnySyncCoordinator::class)->sync();

    Saloon::assertSent(fn ($request): bool => $request instanceof SaveRecordRequest && $request->resolveEndpoint() === '/teams/family/recipes/1');
    expect(PendingWrite::count())->toBe(0)->and(Recipe::find(1)->name)->toBe('Soup');
});

it('drops kept changes when a different or unknown account signs in', function (array $login): void {
    seedSunnyData();
    app(SunnyStore::class)->rememberAccount(5);
    $photo = UploadedFile::fake()->image('photo.jpg');
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1, $photo->getPathname());
    $keptPhoto = PendingWrite::sole()->photo_path;
    Saloon::fake([LogoutRequest::class => MockResponse::make([], 204), CreateTokenRequest::class => MockResponse::make([...$login, 'token' => 'new-token'])]);
    app(SunnyAuth::class)->logout();

    app(SunnyAuth::class)->login('other@example.com', 'password', 'phone');

    expect(PendingWrite::count())->toBe(0)->and(Recipe::count())->toBe(0)->and(Team::count())->toBe(0)
        ->and($keptPhoto)->not->toBeFile();
})->with([
    'different account' => [['id' => 6]],
    'unknown account' => [[]],
]);

it('drops kept changes when the account that signs in cannot reach their team', function (): void {
    seedSunnyData();
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup'], 1);
    Saloon::fake([LogoutRequest::class => MockResponse::make([], 204)]);
    app(SunnyAuth::class)->logout();

    Saloon::fake([SyncRequest::class => MockResponse::make($this->snapshot)]);
    app(SunnySync::class)->sync();

    expect(PendingWrite::count())->toBe(0)->and(Team::pluck('id')->all())->toBe([7])->and(Recipe::find(1))->toBeNull();
});

it('does not expose another API server data', function (): void {
    seedSunnyData();
    config(['services.sunny.api_url' => 'https://other.example/api']);
    expect(Recipes::all())->toBe([])->and(Inventory::all())->toBe([])
        ->and(app(SunnyStore::class)->lastSyncedAt())->toBeNull();
});
