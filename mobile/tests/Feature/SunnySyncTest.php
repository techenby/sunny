<?php

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\Requests\LogoutRequest;
use App\Http\Integrations\Sunny\Requests\RefreshTokenRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Team;
use App\NativeComponents\Inventory;
use App\NativeComponents\Recipes;
use Illuminate\Validation\ValidationException;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Saloon\Config;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    config(['services.sunny.api_url' => 'https://sunny.example/api']);
    Config::preventStrayRequests();
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
    $recipes = Native::visit('/dashboard')->assertDontSee('Soup')->tap('dashboard-recipes-all')->follow();
    $async->assertNotDispatched();
    $this->travel(6)->minutes();
    $recipes->goBack()->assertSee('Soup');
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

it('clears local data when a session is revoked', function (): void {
    seedSunnyData();
    Saloon::fake([SyncRequest::class => MockResponse::make([], 401)]);
    Native::visit('/dashboard')->call('sync')->assertReplacedWith('/login');
    expect(Recipe::count())->toBe(0)->and(Item::count())->toBe(0)->and(app(SunnyStore::class)->lastSyncedAt())->toBeNull();
});

it('clears the previous account data on login or logout but keeps it on token refresh', function (string $action): void {
    seedSunnyData();
    Saloon::fake([
        CreateTokenRequest::class => MockResponse::make(['token' => 'new-token']),
        LogoutRequest::class => MockResponse::make([], 204),
        RefreshTokenRequest::class => MockResponse::make(['token' => 'refreshed-token']),
    ]);
    $auth = app(SunnyAuth::class);
    match ($action) {
        'login' => $auth->login('person@example.com', 'password', 'phone'),
        'logout' => $auth->logout(),
        'refresh' => $auth->refresh(),
    };
    expect(Recipe::count())->toBe($action === 'refresh' ? 6 : 0)
        ->and(Item::count())->toBe($action === 'refresh' ? 15 : 0);
})->with(['login', 'logout', 'refresh']);

it('does not expose another API server data', function (): void {
    seedSunnyData();
    config(['services.sunny.api_url' => 'https://other.example/api']);
    expect(Recipes::all())->toBe([])->and(Inventory::all())->toBe([])
        ->and(app(SunnyStore::class)->lastSyncedAt())->toBeNull();
});
