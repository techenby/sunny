<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Models\Item;
use App\NativeComponents\ScanInventory;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Testing\Native;
use Native\Mobile\Testing\TestableComponent;
use Saloon\Config;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Sunny\ItemScanner\Events\IdentificationFailed;
use Sunny\ItemScanner\Events\ItemsIdentified;

beforeEach(function (): void {
    config(['services.sunny.api_url' => 'https://sunny.example/api']);
    Config::preventStrayRequests();
    seedSunnyData();
});

afterEach(fn () => File::delete(storage_path('framework/testing/shelf.jpg')));

/**
 * Open the scan screen with the on-device model ready and run one photo
 * through it, so the test can deliver the model's answer.
 *
 * @return array{0: TestableComponent, 1: string, 2: string}
 */
function scanPhoto(array $data = []): array
{
    Native::fakeBridge()
        ->respondTo('ItemScanner.Availability', ['available' => true, 'reason' => null])
        ->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    $photo = UploadedFile::fake()->image('shelf.jpg');
    $photoPath = storage_path('framework/testing/shelf.jpg');
    File::ensureDirectoryExists(dirname($photoPath));
    File::copy($photo->getPathname(), $photoPath);

    $screen = Native::visit('/inventory/scan', $data)
        ->emitNative(PhotoTaken::class, ['path' => $photoPath]);

    return [$screen, array_key_first($screen->get('pendingScans')), $photoPath];
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
        ->assertDontSee('Take photo');
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

it('sends a new photo to the model with the place and what is already there', function () {
    [$screen, , $photoPath] = scanPhoto(['parent' => 7]);

    $screen->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params['path'] === $photoPath
        && $params['place'] === 'Garage › Tool chest'
        && $params['knownNames'] === ['Cordless drill', 'Tape measure'])
        ->assertSee('Identifying items in 1 photo…');
});

it('asks the gallery for several images and scans each one', function () {
    Native::fakeBridge()->respondTo('ItemScanner.Availability', ['available' => true, 'reason' => null]);

    Native::visit('/inventory/scan')
        ->tap('scan-choose-photos')
        ->assertNativeCalled('Camera.PickMedia', fn (array $params): bool => $params['mediaType'] === 'image' && $params['multiple'] === true)
        ->emitNative(MediaSelected::class, ['success' => true, 'files' => [['path' => '/tmp/a.jpg'], ['path' => '/tmp/b.jpg']]])
        ->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params['path'] === '/tmp/a.jpg')
        ->assertNativeCalled('ItemScanner.Identify', fn (array $params): bool => $params['path'] === '/tmp/b.jpg')
        ->assertSee('Identifying items in 2 photos…');
});

it('folds repeat sightings into one find and leaves likely duplicates unchecked', function () {
    [$screen, $firstScan, $firstPhoto] = scanPhoto(['parent' => 7]);
    $secondScan = array_key_last($screen->emitNative(PhotoTaken::class, ['path' => '/tmp/closer.jpg'])->get('pendingScans'));

    $screen
        ->emitNative(ItemsIdentified::class, ['id' => $firstScan, 'items' => [
            ['name' => 'Hammer', 'category' => 'Tools', 'brand' => null, 'model' => null, 'quantity' => 1, 'existingMatch' => null],
            ['name' => 'Tape measure', 'category' => 'Tools', 'brand' => 'Stanley', 'model' => null, 'quantity' => 1, 'existingMatch' => 'Tape measure'],
        ]])
        ->emitNative(ItemsIdentified::class, ['id' => $secondScan, 'items' => [
            ['name' => 'hammer', 'category' => 'Tools', 'brand' => 'Estwing', 'model' => 'E3-16C', 'quantity' => 2, 'existingMatch' => null],
        ]]);

    expect($screen->get('candidates'))->toBe([
        ['id' => 1, 'name' => 'Hammer', 'photoPath' => $firstPhoto, 'category' => 'Tools', 'brand' => 'Estwing', 'model' => 'E3-16C', 'quantity' => 2, 'existingMatch' => null, 'selected' => true, 'error' => null],
        ['id' => 2, 'name' => 'Tape measure', 'photoPath' => $firstPhoto, 'category' => 'Tools', 'brand' => 'Stanley', 'model' => null, 'quantity' => 1, 'existingMatch' => 'Tape measure', 'selected' => false, 'error' => null],
    ]);
    $screen->assertSet('pendingScans', [])
        ->assertSee('Estwing · E3-16C · ×2')
        ->assertSee('Might already be here as “Tape measure”')
        ->assertSee('Add 1 item');
});

it('ignores results for scans this screen did not start', function () {
    [$screen] = scanPhoto();

    $screen->emitNative(ItemsIdentified::class, ['id' => 'someone-else', 'items' => [['name' => 'Hammer']]])
        ->assertSet('candidates', [])
        ->assertSee('Identifying items in 1 photo…');
});

it('tells the user when a photo could not be read or had nothing in it', function (string $event, array $payload, string $message) {
    [$screen, $scan] = scanPhoto();

    $screen->emitNative($event, ['id' => $scan, ...$payload])
        ->assertSet('pendingScans', [])
        ->assertSee($message)
        ->tap('scan-errors-dismiss')
        ->assertDontSee($message);
})->with([
    'failure' => [IdentificationFailed::class, ['message' => 'Turn on Apple Intelligence in Settings to scan items.'], 'Turn on Apple Intelligence in Settings to scan items.'],
    'empty' => [ItemsIdentified::class, ['items' => []], 'No items found in that photo. Try getting closer.'],
]);

it('adds the checked finds to Sunny under the chosen place with their details and photo', function () {
    $nextId = 100;
    Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request) use (&$nextId): MockResponse {
        return MockResponse::make(['data' => [
            'id' => $nextId++, 'team_id' => 1, 'type' => 'item', 'parent_id' => 7, 'photo_url' => null,
            'name' => $request->body()->get('name')->value, 'metadata' => null,
        ]], 201);
    }]);
    [$screen, $scan, $photoPath] = scanPhoto(['parent' => 7]);

    $screen->emitNative(ItemsIdentified::class, ['id' => $scan, 'items' => [
        ['name' => 'Hammer', 'category' => 'Tools', 'brand' => 'Estwing', 'model' => null, 'quantity' => 2, 'existingMatch' => null],
        ['name' => 'Level', 'category' => '', 'brand' => null, 'model' => null, 'quantity' => 1, 'existingMatch' => null],
        ['name' => 'Tape measure', 'category' => 'Tools', 'brand' => null, 'model' => null, 'quantity' => 1, 'existingMatch' => 'Tape measure'],
    ]])
        ->call('renameCandidate', 2, 'Torpedo level')
        ->tap('scan-submit')
        ->assertSet('error', '')
        ->assertReplacedWith('/inventory/7');

    $fields = fn (SaveRecordRequest $request): array => collect($request->body()->all())
        ->mapWithKeys(fn ($part): array => [$part->name => $part->name === 'photo' ? (string) $part->value : $part->value])
        ->all();
    Saloon::assertSentCount(2);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $fields($request) === [
        'name' => 'Hammer', 'type' => 'item', 'parent_id' => '7',
        'metadata' => json_encode(['brand' => 'Estwing', 'category' => 'Tools', 'quantity' => '2']),
        'photo' => file_get_contents($photoPath),
    ]);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $fields($request) === [
        'name' => 'Torpedo level', 'type' => 'item', 'parent_id' => '7', 'metadata' => 'null',
        'photo' => file_get_contents($photoPath),
    ]);
    expect(Item::whereIn('id', [100, 101])->where('parent_id', 7)->pluck('name')->all())->toBe(['Hammer', 'Torpedo level']);
});

it('still adds a find whose photo has since been cleared from the phone', function () {
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => [
        'id' => 100, 'team_id' => 1, 'parent_id' => null, 'type' => 'item', 'name' => 'Hammer', 'metadata' => null,
    ]], 201)]);
    [$screen, $scan, $photoPath] = scanPhoto();
    $screen->emitNative(ItemsIdentified::class, ['id' => $scan, 'items' => [['name' => 'Hammer']]]);
    File::delete($photoPath);

    $screen->tap('scan-submit')
        ->assertSet('error', '')
        ->assertReplacedWith('/inventory');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->body()->all() === [
        'name' => 'Hammer', 'type' => 'item', 'parent_id' => null, 'metadata' => null,
    ]);
});

it('stops at the first failed save and keeps what was not added', function () {
    $responses = [
        MockResponse::make(['data' => ['id' => 100, 'team_id' => 1, 'parent_id' => null, 'type' => 'item', 'name' => 'Hammer', 'metadata' => null]], 201),
        MockResponse::make([], 403),
        MockResponse::make(['data' => ['id' => 101, 'team_id' => 1, 'parent_id' => null, 'type' => 'item', 'name' => 'Saw', 'metadata' => null]], 201),
    ];
    Saloon::fake([SaveRecordRequest::class => function () use (&$responses): MockResponse {
        return array_shift($responses);
    }]);
    [$screen, $scan] = scanPhoto();

    $screen->emitNative(ItemsIdentified::class, ['id' => $scan, 'items' => [['name' => 'Hammer'], ['name' => 'Level'], ['name' => 'Saw']]])
        ->tap('scan-submit')
        ->assertNoNavigation()
        ->assertSee('Some items weren’t added. Fix the one marked below and try again.')
        ->assertSee('You no longer have permission to save to this team.');

    Saloon::assertSentCount(2);
    expect(array_column($screen->get('candidates'), 'name'))->toBe(['Level', 'Saw'])
        ->and(Item::find(100)?->name)->toBe('Hammer');
});

it('refuses to add nothing or an unnamed item', function (array $changes, string $message) {
    Saloon::fake([]);
    [$screen, $scan] = scanPhoto();

    $screen->emitNative(ItemsIdentified::class, ['id' => $scan, 'items' => [['name' => 'Hammer']]])
        ->call(...$changes)
        ->call('save')
        ->assertNoNavigation()
        ->assertSet('error', $message);

    Saloon::assertNothingSent();
})->with([
    'nothing checked' => [['toggleCandidate', 1, false], 'Choose at least one item to add.'],
    'blank name' => [['renameCandidate', 1, '   '], 'Give every item a name.'],
]);
