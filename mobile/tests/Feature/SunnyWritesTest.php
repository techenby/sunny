<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

it('saves on the phone, opens the record, then stores what Sunny confirms', function (string $resource, string $route, string $ref, bool $editing): void {
    $model = $resource === 'recipes' ? Recipe::class : Item::class;
    $record = $model::find(1)->toArray();
    $record['id'] = $editing ? 1 : 90;
    $record['name'] = 'Saved by Sunny';
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record], $editing ? 200 : 201)]);
    Native::visit('/'.$route.($editing ? '/1/edit' : '/create'))
        ->set('name', 'Draft name')->tap($ref.'-submit')
        ->assertSet('error', '')->assertReplacedWith('/'.$route.'/'.($editing ? 1 : -1));
    expect($model::find($record['id'])->name)->toBe('Saved by Sunny')
        ->and(PendingWrite::count())->toBe(0);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/'.$resource.($editing ? '/1' : '')
        && $request->getMethod() === ($editing ? Method::PATCH : Method::POST)
        && $request->body()->get('name') === 'Draft name'
        && ($request->body()->get('client_uuid') !== null) === ! $editing);
})->with([
    ['recipes', 'recipes', 'create-recipe', false], ['recipes', 'recipes', 'edit-recipe', true],
    ['items', 'inventory', 'create-item', false], ['items', 'inventory', 'edit-item', true],
]);

it('finds a new record by the id it opened with after Sunny assigns one', function (): void {
    $record = [...Recipe::find(1)->toArray(), 'id' => 90, 'name' => 'Soup'];
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record], 201)]);
    Native::visit('/recipes/create')->set('name', 'Soup')->tap('create-recipe-submit')->assertReplacedWith('/recipes/-1');

    Native::visit('/recipes/-1')->assertSee('Soup')->assertMissingElement('row', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-sync-pending');
    expect(Recipe::find(90)->local_id)->toBe(-1);
});

it('sends the entire recipe form and preserves unedited rich text', function (): void {
    Recipe::find(1)->update(['ingredients' => '<p><strong>Flour</strong></p>']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => Recipe::find(1)->toArray()])]);
    Native::visit('/recipes/1/edit')->set('tags', ['Vegan'])->set('nutrition', 'Protein: 2g')->set('notes', '')
        ->set('instructions', 'Mix')->tap('edit-recipe-submit')->assertSet('error', '');
    Saloon::assertSent(function (SaveRecordRequest $request): bool {
        $body = $request->body()->all();
        expect($body)->not->toHaveKey('ingredients');
        expect($body['tags'])->toBe(['Vegan']);
        expect($body['nutrition'])->toBe('Protein: 2g');
        expect($body['notes'])->toBeNull();
        expect($body['instructions'])->toBe('<ol><li>Mix</li></ol>');

        return true;
    });
});

it('leaves unedited rich text out even when a sync changes it while the form is open', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => Recipe::find(1)->toArray()])]);
    $harness = Native::visit('/recipes/1/edit');
    Recipe::find(1)->update(['ingredients' => '<ul><li><strong>Flour</strong> from the web</li></ul>', 'instructions' => '<p>Edited remotely</p>']);
    $harness->set('name', 'Weekend Pancakes')->tap('edit-recipe-submit')->assertSet('error', '');
    Saloon::assertSent(function (SaveRecordRequest $request): bool {
        expect($request->body()->all())->not->toHaveKeys(['ingredients', 'instructions']);

        return true;
    });
});

it('only sends remove_photo when editing a recipe', function (): void {
    $record = Recipe::find(1)->toArray();
    $record['id'] = 90;
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record], 201)]);
    Native::visit('/recipes/create')->set('name', 'New recipe')->tap('create-recipe-submit')->assertSet('error', '');
    Saloon::assertSent(function (SaveRecordRequest $request): bool {
        expect($request->body()->all())->not->toHaveKey('remove_photo');

        return true;
    });
});

it('stores fields the save response leaves out as empty locally', function (): void {
    Recipe::find(1)->update(['notes' => 'Old local note']);
    $record = Arr::except(Recipe::find(1)->toArray(), ['notes', 'server']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record])]);
    Native::visit('/recipes/1/edit')->tap('edit-recipe-submit')->assertSet('error', '');

    expect(Recipe::find(1)->notes)->toBeNull();
});

it('uploads a photo with method spoofing and retains metadata keys', function (): void {
    $photo = UploadedFile::fake()->image('photo.jpg');
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => Item::find(1)->toArray()])]);
    Native::visit('/inventory/1/edit')->set('photoPath', $photo->getPathname())
        ->set('metadata', [['key' => 'size[inches]', 'value' => '12']])
        ->tap('edit-item-submit')->assertSet('error', '');
    Saloon::assertSent(function (SaveRecordRequest $request) use ($photo): bool {
        expect($request->getMethod())->toBe(Method::POST);
        expect($request->body()->get('_method')->value)->toBe('PATCH');
        expect($request->body()->get('remove_photo')->value)->toBe('0');
        expect(json_decode($request->body()->get('metadata')->value, true))->toBe(['size[inches]' => '12']);
        expect((string) $request->body()->get('photo')->value)->toBe(file_get_contents($photo->getPathname()));

        return true;
    });
});

it('removes an existing recipe photo explicitly', function (): void {
    Recipe::find(1)->update(['photo_url' => 'https://sunny.example/photo.jpg']);
    $record = Recipe::find(1)->toArray();
    $record['photo_url'] = null;
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record])]);
    Native::visit('/recipes/1/edit')->tap('edit-recipe-photo-remove')->tap('edit-recipe-submit')->assertSet('error', '');
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->body()->get('remove_photo') === true);
    expect(Recipe::find(1)->photo_url)->toBeNull();
});

it('keeps a change Sunny refuses on the phone and explains why', function (int $status, string $message): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['errors' => ['name' => ['That name is taken.']]], $status)]);
    Native::visit('/recipes/1/edit')->set('name', 'Unsaved')->tap('edit-recipe-submit')->assertReplacedWith('/recipes/1');

    expect(Recipe::find(1)->name)->toBe('Unsaved')
        ->and(PendingWrite::sole()->error)->toBe($message);
    Native::visit('/recipes/1')->assertSee('Not saved to Sunny')->assertSee($message);
})->with([
    [422, 'That name is taken.'], [403, 'You no longer have permission to save to this team.'],
    [404, 'This record or team is no longer on Sunny.'],
]);

it('keeps sending a change after Sunny fails for a reason that may pass', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 500)]);
    Native::visit('/recipes/1/edit')->set('name', 'Unsaved')->tap('edit-recipe-submit')->assertReplacedWith('/recipes/1');

    expect(PendingWrite::sole()->error)->toBeNull();
    Native::visit('/recipes/1')->assertSee('Saved on this phone · syncing with Sunny');
    expect(app(SunnySyncCoordinator::class)->isDue())->toBeTrue();
});

it('discards a refused edit and leaves the record for the next download', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 403)]);
    Native::visit('/recipes/1/edit')->set('name', 'Unsaved')->tap('edit-recipe-submit');

    Native::visit('/recipes/1')->tap('recipe-sync-discard')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'discard-queued-change'
            && $params['message'] === 'Your edit will be replaced by the recipe on Sunny the next time this phone syncs.')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Discard', 'id' => 'discard-queued-change'])
        ->assertNoNavigation()->assertDontSee('Not saved to Sunny');
    expect(PendingWrite::count())->toBe(0);
});

it('keeps a refused change when discarding it is cancelled', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 403)]);
    Native::visit('/recipes/1/edit')->set('name', 'Unsaved')->tap('edit-recipe-submit');

    Native::visit('/recipes/1')->tap('recipe-sync-discard')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'discard-queued-change'])
        ->assertSee('Not saved to Sunny');
    expect(PendingWrite::sole()->error)->not->toBeNull()->and(Recipe::find(1)->name)->toBe('Unsaved');
});

it('discards a refused new item, moving what was put inside it to the top level', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 403)]);
    $outbox = app(SunnyOutbox::class);
    $shelf = $outbox->queue('items', 1, ['name' => 'Shelf', 'type' => 'bin', 'parent_id' => null, 'metadata' => null]);
    $outbox->queue('items', 1, ['name' => 'Hammer', 'type' => 'item', 'parent_id' => $shelf, 'metadata' => null]);
    app(SunnyWrites::class)->push();

    Native::visit('/inventory/'.$shelf)->tap('item-sync-discard')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['message'] === 'This item never reached Sunny, so it will be deleted from this phone.')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Discard', 'id' => 'discard-queued-change'])
        ->assertWentBack();

    expect(Item::find($shelf))->toBeNull()
        ->and(Item::firstWhere('name', 'Hammer')->parent_id)->toBeNull()
        ->and(PendingWrite::sole()->payload['parent_id'])->toBeNull();
});

it('sends a new item only after the new item it is inside has an id', function (): void {
    $nextId = 100;
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request) use (&$nextId): MockResponse {
        return MockResponse::make(['data' => [...$request->body()->all(), 'id' => $nextId++, 'team_id' => 1]], 201);
    }]);
    $outbox = app(SunnyOutbox::class);
    $hammer = $outbox->queue('items', 1, ['name' => 'Hammer', 'type' => 'item', 'parent_id' => null, 'metadata' => null]);
    $shelf = $outbox->queue('items', 1, ['name' => 'Shelf', 'type' => 'bin', 'parent_id' => null, 'metadata' => null]);
    $outbox->queue('items', 1, ['name' => 'Hammer', 'type' => 'item', 'parent_id' => $shelf, 'metadata' => null], $hammer);

    app(SunnyWrites::class)->push();

    expect(Item::find(100)->name)->toBe('Shelf')
        ->and(Item::find(101)->parent_id)->toBe(100)
        ->and(PendingWrite::count())->toBe(0);
});

it('sends an edit made while the new record was being created as a follow-up update', function (): void {
    $outbox = app(SunnyOutbox::class);
    $id = $outbox->queue('recipes', 1, ['name' => 'Soup']);
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request) use ($outbox, $id): MockResponse {
        if ($request->getMethod() === Method::POST) {
            $outbox->queue('recipes', 1, ['name' => 'Tomato soup'], $id);
        }

        return MockResponse::make(['data' => [...Recipe::find(1)->toArray(), 'id' => 90, 'name' => $request->body()->get('name')]]);
    }]);

    app(SunnyWrites::class)->push();

    Saloon::assertSentCount(2);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/recipes/90'
        && $request->body()->get('name') === 'Tomato soup' && $request->body()->get('client_uuid') === null);
    expect(Recipe::find(90)->name)->toBe('Tomato soup')->and(PendingWrite::count())->toBe(0);
});

it('folds a second edit into the change still waiting, keeping an earlier photo removal', function (): void {
    Recipe::find(1)->update(['photo_url' => 'https://sunny.example/photo.jpg']);
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('recipes', 1, ['name' => 'First', 'remove_photo' => true], 1);
    $outbox->queue('recipes', 1, ['notes' => 'Second', 'remove_photo' => false], 1);

    expect(PendingWrite::sole()->payload)->toBe(['name' => 'First', 'remove_photo' => true, 'notes' => 'Second'])
        ->and(Recipe::find(1)->only('name', 'notes', 'photo_url'))->toBe(['name' => 'First', 'notes' => 'Second', 'photo_url' => null]);
});

it('retries a new record with the same client id so Sunny can spot the repeat', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 503)]);
    $id = app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup']);
    $uuid = PendingWrite::sole()->client_uuid;
    expect(fn () => app(SunnyWrites::class)->push())->toThrow(RequestException::class);

    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [...Recipe::find(1)->toArray(), 'id' => 90]], 200)]);
    app(SunnyWrites::class)->push();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->body()->get('client_uuid') === $uuid);
    expect(Recipe::find($id))->toBeNull()->and(Recipe::find(90))->not->toBeNull();
});

it('keeps queued changes when a download lands before they are sent', function (): void {
    $id = app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Soup']);
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Local edit'], 2);
    $uuid = PendingWrite::firstWhere('record_id', $id)->client_uuid;
    $snapshot = [
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
        'recipes' => [[...Recipe::find(2)->toArray(), 'name' => 'Remote edit'], [...Recipe::find(1)->toArray(), 'id' => 90, 'name' => 'Soup', 'client_uuid' => $uuid]],
        'items' => [], 'routines' => [], 'routine_occurrences' => [], 'synced_at' => now()->toIso8601String(),
    ];
    Saloon::fake([SyncRequest::class => MockResponse::make($snapshot)]);

    app(SunnySync::class)->sync();

    expect(Recipe::pluck('name', 'id')->all())->toBe([$id => 'Soup', 2 => 'Local edit']);
});

it('uses the active team and filters parents by team', function (): void {
    Team::create(['id' => 2, 'name' => 'Work', 'slug' => 'work', 'server' => SunnyStore::server()]);
    Item::create(['id' => 99, 'team_id' => 2, 'name' => 'Work bin', 'type' => 'bin', 'server' => SunnyStore::server()]);
    Native::visit('/dashboard')->tap('active-team')->tap('team-2');
    $screen = Native::visit('/inventory/create')->assertSet('teamId', 2);
    expect(array_column($screen->get('parentChoices'), 'name'))->toBe(['Work bin']);
});

it('saves a new recipe on the phone while Sunny is unreachable', function (): void {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 503)]);
    Native::visit('/recipes/create')->set('name', 'Offline draft')->tap('create-recipe-submit')
        ->assertReplacedWith('/recipes/-1');
    Native::visit('/recipes/-1')->assertSee('Offline draft')->assertSee('Saved on this phone · syncing with Sunny');
    Native::visit('/recipes')->assertSee('Offline draft');
});

it('rejects a missing photo before sending a request', function (): void {
    Saloon::fake([]);
    Native::visit('/recipes/create')->set('name', 'Cake')->set('photoPath', '/missing-sunny-photo.jpg')
        ->tap('create-recipe-submit')->assertNoNavigation()
        ->assertSee('Choose the photo again; its file is no longer available.');
    Saloon::assertNothingSent();
});

it('flags responses for another team without writing them locally', function (): void {
    $record = Recipe::find(1)->toArray();
    $record['team_id'] = 999;
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record])]);
    Native::visit('/recipes/1/edit')->set('name', 'Draft')->tap('edit-recipe-submit');
    expect(Recipe::find(1)->team_id)->toBe(1)->and(Recipe::find(1)->name)->toBe('Draft')
        ->and(PendingWrite::sole()->error)->toBe('Sunny didn’t confirm this change. Edit it to try again.');
});

it('syncs team route keys and remote photo URLs for subsequent edits', function (): void {
    $snapshot = [
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family-home']],
        'recipes' => [Recipe::find(1)->toArray()], 'items' => [], 'routines' => [], 'routine_occurrences' => [], 'synced_at' => now()->toIso8601String(),
    ];
    $snapshot['recipes'][0]['photo_url'] = 'https://sunny.example/photo.jpg';
    Saloon::fake([SyncRequest::class => MockResponse::make($snapshot)]);
    app(SunnySync::class)->sync();
    expect(Team::find(1)->slug)->toBe('family-home');
    Native::visit('/recipes/1/edit')->assertSet('existingPhotoUrl', 'https://sunny.example/photo.jpg');
});
