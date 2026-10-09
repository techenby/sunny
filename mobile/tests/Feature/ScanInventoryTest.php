<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\ScanDraft;
use App\NativeComponents\ScanInventory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Sunny\ItemScanner\Events\CaptureFailed;
use Sunny\ItemScanner\Events\IdentificationFailed;
use Sunny\ItemScanner\Events\ItemIdentified;
use Sunny\ItemScanner\Events\ItemRepeated;
use Sunny\ItemScanner\Events\PhotoCaptured;

beforeEach(function (): void {
    seedSunnyData();
});

afterEach(function (): void {
    File::delete(storage_path('framework/testing/shelf.jpg'));
    File::deleteDirectory(ScanDraft::photoDirectory());
});

function fakeScanner(): void
{
    Native::fakeBridge()
        ->respondTo('ItemScanner.Availability', ['available' => true, 'reason' => null])
        ->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
}

/**
 * @return array{0: TestableComponent, 1: string, 2: string}
 */
function scanPhoto(array $data = []): array
{
    fakeScanner();
    $photo = UploadedFile::fake()->image('shelf.jpg');
    $photoPath = storage_path('framework/testing/shelf.jpg');
    File::ensureDirectoryExists(dirname($photoPath));
    File::copy($photo->getPathname(), $photoPath);

    $screen = Native::visit('/inventory/scan', $data)
        ->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => $photoPath]);

    return [$screen, $screen->get('candidates')[0]['scanId'], $photoPath];
}

/**
 * @param  list<string>  $names
 */
function scanNamedPhotos(array $names): TestableComponent
{
    [$screen, $scan] = scanPhoto();
    $screen->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => array_shift($names)]]);

    foreach ($names as $name) {
        $screen->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => '/tmp/'.$name.'.jpg']);
        $screen->emitNative(ItemIdentified::class, ['id' => $screen->get('candidates')[0]['scanId'], 'item' => ['name' => $name]]);
    }

    return $screen;
}

it('explains why scanning is unavailable instead of offering the camera', function (?array $availability, string $message) {
    $bridge = Native::fakeBridge();

    if ($availability !== null) {
        $bridge->respondTo('ItemScanner.Availability', $availability);
    } else {
        $bridge->withoutCapability('ItemScanner.Availability');
    }

    Native::visit('/inventory/scan')
        ->assertSee($message)
        ->assertDontSee('What are you scanning?');
})->with([
    'android' => [null, 'Scanning uses Apple Intelligence, so it only works on iPhone for now.'],
    'older iOS' => [['available' => false, 'reason' => 'unsupportedOS'], 'Scanning needs iOS 27 or later. Update your iPhone to use it.'],
    'ineligible device' => [['available' => false, 'reason' => 'deviceNotEligible'], 'This iPhone can’t run Apple Intelligence, which scanning needs.'],
    'Apple Intelligence off' => [['available' => false, 'reason' => 'appleIntelligenceNotEnabled'], 'Turn on Apple Intelligence in Settings to scan items.'],
    'model downloading' => [['available' => false, 'reason' => 'modelNotReady'], 'Apple Intelligence is still getting ready. Try again in a few minutes.'],
]);

it('opens the scan screen from the inventory list', function () {
    Native::visit('/inventory')
        ->tap('inventory-scan')
        ->assertNavigatedTo('/inventory/scan')
        ->follow()
        ->assertScreen(ScanInventory::class)
        ->assertNavTitle('Scan items');
});

it('scans into the container it was opened from', function () {
    Native::fakeBridge()->respondTo('ItemScanner.Availability', ['available' => true, 'reason' => null]);

    Native::visit('/inventory/7')
        ->tap('item-scan-children')
        ->assertNavigatedTo('/inventory/scan')
        ->follow()
        ->assertSet('parentId', 7)
        ->assertSee('Tool chest');
});

it('opens a camera that stays open between shots', function () {
    fakeScanner();

    Native::visit('/inventory/scan')
        ->tap('scan-take-photos')
        ->assertNativeCalled('ItemScanner.Capture', fn (array $params): bool => $params === ['id' => 'scan', 'single' => false]);
});

it('lists each new photo right away, newest first, and sends it to the model with the batch', function () {
    [$screen, $firstScan, $photoPath] = scanPhoto(['parent' => 7]);
    $keptPhoto = $screen->get('candidates')[0]['photoPath'];

    expect($keptPhoto)->toStartWith(ScanDraft::photoDirectory().'/')
        ->and(file_get_contents($keptPhoto))->toBe(file_get_contents($photoPath))
        ->and($screen->get('candidates'))->toBe([
            ['id' => 1, 'name' => '', 'suggestedName' => '', 'photoPath' => $keptPhoto, 'category' => '', 'quantity' => 1, 'extraCopies' => 0, 'scanId' => $firstScan, 'scanError' => null, 'error' => null],
        ]);
    $screen->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params === [
        'path' => $keptPhoto, 'id' => $firstScan, 'place' => 'Garage › Tool chest', 'batch' => null, 'batchNames' => [],
    ])
        ->assertSee('1 item · 1 identifying…')
        ->assertSee('Add 1 item');

    $screen->call('updateBatch', ' Christmas ornaments ')
        ->emitNative(ItemIdentified::class, ['id' => $firstScan, 'item' => ['name' => 'Glass snowman']])
        ->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => '/tmp/second.jpg'])
        ->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params['path'] === '/tmp/second.jpg'
            && $params['batch'] === 'Christmas ornaments'
            && $params['batchNames'] === ['Glass snowman']);

    expect(collect($screen->get('candidates'))->pluck('id')->all())->toBe([2, 1]);
    $screen->assertSee('2 items · 1 identifying…');
});

it('counts another copy of the last photographed item with +1', function () {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [
        'id' => 100, 'team_id' => 1, 'parent_id' => null, 'type' => 'item', 'name' => 'Red bauble', 'metadata' => null,
    ]], 201)]);
    [$screen, $scan] = scanPhoto();

    $screen->emitNative(ItemRepeated::class, ['id' => 'scan'])
        ->emitNative(ItemRepeated::class, ['id' => 'scan'])
        ->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => 'Red bauble', 'category' => 'Decor', 'quantity' => 2]])
        ->assertSee('Decor · ×4');

    expect($screen->get('candidates')[0])->toMatchArray(['quantity' => 2, 'extraCopies' => 2])
        ->and(ScanDraft::sole()->candidates[0]['extraCopies'])->toBe(2);

    $screen->tap('scan-submit')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Add', 'id' => 'add-items']);

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->body()->get('metadata')->value === json_encode(['category' => 'Decor', 'quantity' => '4']));
});

it('ignores +1 when no photo has been taken or it came from another camera', function () {
    fakeScanner();
    $screen = Native::visit('/inventory/scan')
        ->emitNative(MediaSelected::class, ['success' => true, 'files' => [['path' => '/tmp/a.jpg']]])
        ->emitNative(ItemRepeated::class, ['id' => 'scan']);

    expect($screen->get('candidates')[0]['extraCopies'])->toBe(0);

    $screen->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => '/tmp/b.jpg'])
        ->emitNative(ItemRepeated::class, ['id' => 'retake-2']);

    expect(collect($screen->get('candidates'))->pluck('extraCopies')->all())->toBe([0, 0]);
});

it('ignores photos from a camera this screen did not open', function () {
    [$screen] = scanPhoto();

    $screen->emitNative(PhotoCaptured::class, ['id' => 'someone-else', 'path' => '/tmp/other.jpg']);

    expect($screen->get('candidates'))->toHaveCount(1);
});

it('explains when the camera could not open', function () {
    fakeScanner();

    Native::visit('/inventory/scan')
        ->emitNative(CaptureFailed::class, ['id' => 'scan', 'message' => 'Allow camera access for Sunny in Settings to scan items.'])
        ->assertSee('Allow camera access for Sunny in Settings to scan items.');
});

it('lists each image chosen from the gallery and scans it', function () {
    fakeScanner();

    $screen = Native::visit('/inventory/scan')
        ->tap('scan-choose-photos')
        ->assertNativeCalled('Camera.PickMedia', fn (array $params): bool => $params['mediaType'] === 'image' && $params['multiple'] === true)
        ->emitNative(MediaSelected::class, ['success' => true, 'files' => [['path' => '/tmp/a.jpg'], ['path' => '/tmp/b.jpg']]])
        ->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params['path'] === '/tmp/a.jpg')
        ->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params['path'] === '/tmp/b.jpg');

    expect(collect($screen->get('candidates'))->pluck('photoPath')->all())->toBe(['/tmp/b.jpg', '/tmp/a.jpg']);
});

it('fills in the name and details the model found', function () {
    [$screen, $scan] = scanPhoto();

    $screen->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => [
        'name' => 'Hammer', 'category' => 'Tools', 'quantity' => 2,
    ]]);

    expect($screen->get('candidates')[0])->toMatchArray([
        'name' => '', 'suggestedName' => 'Hammer', 'category' => 'Tools', 'quantity' => 2, 'scanId' => null, 'scanError' => null,
    ]);
    $screen->assertSee('Tools · ×2')
        ->assertSee('1 item')
        ->assertDontSee('identifying…');
});

it('offers the model’s name as a suggestion without touching what the user typed', function () {
    [$screen, $scan] = scanPhoto();

    $screen->call('renameCandidate', 1, 'Framing hammer')
        ->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => 'Hammer', 'category' => 'Tools']]);

    expect($screen->get('candidates')[0])->toMatchArray(['name' => 'Framing hammer', 'suggestedName' => 'Hammer', 'category' => 'Tools', 'scanId' => null]);
});

it('copies the suggestion into the name so it can be edited', function () {
    [$screen, $scan] = scanPhoto();

    $screen->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => 'Glass snowman']])
        ->assertSee('Use “Glass snowman”')
        ->tap('scan-candidate-1-use-suggestion')
        ->assertDontSee('Use “Glass snowman”');

    expect($screen->get('candidates')[0])->toMatchArray(['name' => 'Glass snowman', 'suggestedName' => 'Glass snowman']);
});

it('ignores results for scans this screen did not start', function () {
    [$screen, $scan] = scanPhoto();

    $screen->emitNative(ItemIdentified::class, ['id' => 'someone-else', 'item' => ['name' => 'Hammer']]);

    expect($screen->get('candidates')[0])->toMatchArray(['name' => '', 'scanId' => $scan]);
});

it('keeps the photo in the list when the model could not name it', function (string $event, array $payload, string $message) {
    [$screen, $scan] = scanPhoto();

    $screen->emitNative($event, ['id' => $scan, ...$payload])
        ->assertSee($message);

    expect($screen->get('candidates')[0])->toMatchArray(['name' => '', 'suggestedName' => '', 'scanId' => null, 'scanError' => $message]);
})->with([
    'failure' => [IdentificationFailed::class, ['message' => 'Turn on Apple Intelligence in Settings to scan items.'], 'Turn on Apple Intelligence in Settings to scan items.'],
    'no name' => [ItemIdentified::class, ['item' => ['name' => '  ']], 'Couldn’t tell what that is. Type a name instead.'],
]);

it('logs why the model could not identify a photo', function () {
    Log::spy();
    [$screen, $scan] = scanPhoto();

    $screen->emitNative(IdentificationFailed::class, ['id' => $scan, 'message' => 'Couldn’t identify that item. Type a name instead.', 'detail' => 'decodingFailure']);

    Log::shouldHaveReceived('warning')->once()->with('Item identification failed', ['message' => 'Couldn’t identify that item. Type a name instead.', 'detail' => 'decodingFailure']);
});

it('retakes a photo and identifies it again without losing a typed name', function () {
    [$screen, $firstScan] = scanPhoto();
    $firstPhoto = $screen->get('candidates')[0]['photoPath'];

    $screen->call('renameCandidate', 1, 'Framing hammer')
        ->tap('scan-candidate-1-retake')
        ->assertNativeCalled('ItemScanner.Capture', fn (array $params): bool => $params === ['id' => 'retake-1', 'single' => true])
        ->emitNative(PhotoCaptured::class, ['id' => 'retake-1', 'path' => '/tmp/closer.jpg'])
        ->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params['path'] === '/tmp/closer.jpg');

    $secondScan = $screen->get('candidates')[0]['scanId'];
    $screen->emitNative(ItemIdentified::class, ['id' => $firstScan, 'item' => ['name' => 'Rock', 'category' => 'Outdoors']])
        ->emitNative(ItemIdentified::class, ['id' => $secondScan, 'item' => ['name' => 'Hammer', 'category' => 'Tools']]);

    expect($screen->get('candidates'))->toHaveCount(1)
        ->and($screen->get('candidates')[0])->toMatchArray(['name' => 'Framing hammer', 'photoPath' => '/tmp/closer.jpg', 'category' => 'Tools', 'scanId' => null])
        ->and($firstPhoto)->not->toBeFile();
});

it('removes an item and its photo from the list', function () {
    $screen = scanNamedPhotos(['Hammer', 'Level']);
    $hammerPhoto = $screen->get('candidates')[1]['photoPath'];

    $screen->tap('scan-candidate-1-remove')
        ->assertSee('Add 1 item');

    expect(collect($screen->get('candidates'))->pluck('suggestedName')->all())->toBe(['Level'])
        ->and($hammerPhoto)->not->toBeFile();
});

it('picks up where it left off after the screen is closed', function () {
    [$screen, $scan] = scanPhoto(['parent' => 7]);
    $screen->call('updateBatch', 'Christmas ornaments')
        ->call('updateCategory', 'Holiday')
        ->call('renameCandidate', 1, 'Glass snowman')
        ->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => '/tmp/reindeer.jpg']);

    $reopened = Native::visit('/inventory/scan')
        ->assertSet('parentId', 7)
        ->assertSet('batch', 'Christmas ornaments')
        ->assertSet('category', 'Holiday')
        ->assertSet('nextCandidateId', 3);

    expect(collect($reopened->get('candidates'))->pluck('name', 'id')->all())->toBe([2 => '', 1 => 'Glass snowman'])
        ->and($reopened->get('candidates')[0]['scanId'])->not->toBeNull()->not->toBe($screen->get('candidates')[0]['scanId']);
    $reopened->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params['path'] === '/tmp/reindeer.jpg' && $params['id'] === $reopened->get('candidates')[0]['scanId']);
});

it('restores drafts saved by earlier versions of the screen', function () {
    fakeScanner();
    ScanDraft::create(['server' => SunnyStore::server(), 'team_id' => 1, 'next_candidate_id' => 2, 'candidates' => [
        ['id' => 1, 'name' => 'Angel ornament', 'nameEdited' => true, 'photoPath' => '/tmp/angel.jpg', 'category' => '', 'brand' => 'Snowman Red Plastic', 'model' => null, 'quantity' => 1, 'scanId' => null, 'scanError' => null, 'error' => null],
    ]]);

    $screen = Native::visit('/inventory/scan');

    expect($screen->get('candidates'))->toBe([
        ['suggestedName' => '', 'extraCopies' => 0, 'id' => 1, 'name' => 'Angel ornament', 'photoPath' => '/tmp/angel.jpg', 'category' => '', 'quantity' => 1, 'scanId' => null, 'scanError' => null, 'error' => null],
    ]);
});

it('starts over after confirming', function () {
    $screen = scanNamedPhotos(['Hammer']);
    $photo = $screen->get('candidates')[0]['photoPath'];

    $screen->tap('scan-start-over')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'start-over')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'start-over']);
    expect($screen->get('candidates'))->toHaveCount(1);

    $screen->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Start over', 'id' => 'start-over'])
        ->assertSet('candidates', []);

    expect(ScanDraft::count())->toBe(0)
        ->and($photo)->not->toBeFile();
});

it('forgets drafts when a different account signs in', function () {
    $screen = scanNamedPhotos(['Hammer']);
    $photo = $screen->get('candidates')[0]['photoPath'];

    app(SunnyStore::class)->startSession(999);

    expect(ScanDraft::count())->toBe(0)
        ->and($photo)->not->toBeFile();
});

it('adds every item to Sunny under the chosen place with its details and photo', function () {
    $nextId = 100;
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request) use (&$nextId): MockResponse {
        return MockResponse::make(['data' => [
            'id' => $nextId++, 'team_id' => 1, 'type' => 'item', 'parent_id' => 7, 'photo_url' => null,
            'name' => $request->body()->get('name')->value, 'metadata' => null,
        ]], 201);
    }]);
    [$screen, $scan, $photoPath] = scanPhoto(['parent' => 7]);

    $screen->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => 'Hammer', 'category' => 'Tools', 'quantity' => 2]])
        ->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => $photoPath])
        ->emitNative(ItemIdentified::class, ['id' => $screen->get('candidates')[0]['scanId'], 'item' => ['name' => 'Level', 'category' => '', 'quantity' => 1]])
        ->call('renameCandidate', 2, 'Torpedo level')
        ->call('updateCategory', '')
        ->tap('scan-submit')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Add', 'id' => 'add-items'])
        ->assertSet('error', '')
        ->assertWentBack();

    $fields = fn (SaveRecordRequest $request): array => collect($request->body()->all())
        ->reject(fn ($part): bool => $part->name === 'client_uuid')
        ->mapWithKeys(fn ($part): array => [$part->name => $part->name === 'photo' ? (string) $part->value : $part->value])
        ->all();
    Saloon::assertSentCount(2);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $fields($request) === [
        'name' => 'Hammer', 'type' => 'item', 'parent_id' => '7',
        'metadata' => json_encode(['category' => 'Tools', 'quantity' => '2']),
        'photo' => file_get_contents($photoPath),
    ]);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $fields($request) === [
        'name' => 'Torpedo level', 'type' => 'item', 'parent_id' => '7', 'metadata' => 'null',
        'photo' => file_get_contents($photoPath),
    ]);
    expect(Item::whereIn('id', [100, 101])->where('parent_id', 7)->pluck('name')->all())->toBe(['Hammer', 'Torpedo level'])
        ->and(ScanDraft::count())->toBe(0)
        ->and(File::files(ScanDraft::photoDirectory()))->toBe([]);
});

it('asks before adding the items', function () {
    Saloon::fake([]);
    [$screen, $scan] = scanPhoto(['parent' => 7]);

    $screen->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => 'Glass snowman']])
        ->tap('scan-submit')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'add-items'
            && $params['title'] === 'Add 1 item?'
            && $params['message'] === 'They’ll be added to Garage › Tool chest.')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'add-items'])
        ->assertNoNavigation();

    Saloon::assertNothingSent();
    expect($screen->get('candidates'))->toHaveCount(1);
});

it('uses the batch category for every item', function () {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [
        'id' => 100, 'team_id' => 1, 'parent_id' => null, 'type' => 'item', 'name' => 'Glass snowman', 'metadata' => null,
    ]], 201)]);
    [$screen, $scan] = scanPhoto();

    $screen->call('updateCategory', 'Holiday')
        ->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => 'Glass snowman', 'category' => 'Decor']])
        ->tap('scan-submit')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Add', 'id' => 'add-items'])
        ->assertSet('error', '');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->body()->get('metadata')->value === json_encode(['category' => 'Holiday']));
});

it('still adds an item whose photo has since been cleared from the phone', function () {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [
        'id' => 100, 'team_id' => 1, 'parent_id' => null, 'type' => 'item', 'name' => 'Hammer', 'metadata' => null,
    ]], 201)]);
    [$screen, $scan] = scanPhoto();
    $screen->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => 'Hammer']]);
    File::delete($screen->get('candidates')[0]['photoPath']);

    $screen->tap('scan-submit')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Add', 'id' => 'add-items'])
        ->assertSet('error', '')
        ->assertWentBack();

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => Arr::except($request->body()->all(), 'client_uuid') === [
        'name' => 'Hammer', 'type' => 'item', 'parent_id' => null, 'metadata' => null,
    ]);
});

it('adds every item and flags the one Sunny refuses', function () {
    $responses = [
        MockResponse::make(['data' => ['id' => 100, 'team_id' => 1, 'parent_id' => null, 'type' => 'item', 'name' => 'Hammer', 'metadata' => null]], 201),
        MockResponse::make([], 403),
        MockResponse::make(['data' => ['id' => 101, 'team_id' => 1, 'parent_id' => null, 'type' => 'item', 'name' => 'Saw', 'metadata' => null]], 201),
    ];
    Saloon::fake([SaveRecordRequest::class => function () use (&$responses): MockResponse {
        return array_shift($responses);
    }]);
    scanNamedPhotos(['Hammer', 'Level', 'Saw'])
        ->tap('scan-submit')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Add', 'id' => 'add-items'])
        ->assertSet('error', '')
        ->assertWentBack();

    Saloon::assertSentCount(3);
    expect(Item::whereIn('id', [100, 101])->pluck('name')->all())->toBe(['Hammer', 'Saw'])
        ->and(PendingWrite::sole()->error)->toBe('You no longer have permission to save to this team.');
    Native::visit('/inventory/'.PendingWrite::sole()->record_id)->assertSee('Level')->assertSee('Not saved to Sunny');
});

it('refuses to add nothing, an unnamed item, or one still being identified', function (?string $name, array $changes, string $message) {
    Saloon::fake([]);
    [$screen, $scan] = scanPhoto();

    if ($name !== null) {
        $screen->emitNative(ItemIdentified::class, ['id' => $scan, 'item' => ['name' => $name]]);
    }

    $screen->call(...$changes)
        ->tap('scan-submit')
        ->assertNativeNotCalled('Dialog.Alert')
        ->assertNoNavigation()
        ->assertSet('error', $message);

    Saloon::assertNothingSent();
})->with([
    'nothing left' => ['Hammer', ['removeCandidate', 1], 'Take a photo of at least one item.'],
    'blank name' => ['  ', ['renameCandidate', 1, '   '], 'Give every item a name.'],
    'still identifying' => [null, ['renameCandidate', 1, ''], 'Some items are still being identified. Wait a moment or name them yourself.'],
]);
