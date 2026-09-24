<?php

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\NativeComponents\CreateInventoryItem;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;

beforeEach(fn () => seedSunnyData());

it('opens the create screen from the inventory list', function () {
    Native::visit('/inventory')
        ->tap('New item')
        ->assertNavigatedTo('/inventory/create')
        ->follow()
        ->assertScreen(CreateInventoryItem::class)
        ->assertNavTitle('New item');
});

it('renders the form', function () {
    Native::visit('/inventory/create')
        ->assertSee('Name')
        ->assertSee('Type')
        ->assertSee('Inside')
        ->assertSee('Save item')
        ->assertElement('button_group', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-type'
            && ($node['props']['options'] ?? null) === ['Location', 'Bin', 'Item'])
        ->assertAccessible();
});

it('offers every item as a destination, plus the top level', function () {
    Native::visit('/inventory/create')
        ->assertElement('select', fn (array $node): bool => ($node['props']['options'] ?? null) === [
            'Top level', 'Basement', 'Basmati rice', 'Camping tent', 'Canned tomatoes', 'Cordless drill',
            'Garage', 'Holiday decorations', 'Kitchen', 'Olive oil', 'Ornaments', 'Pantry',
            'Spare light bulbs', 'String lights', 'Tape measure', 'Tool chest',
        ]);
});

it('binds the form fields', function () {
    Native::visit('/inventory/create')
        ->input('create-item-name', 'Sleeping bag')
        ->select('create-item-parent', 'Garage')
        ->assertSet('name', 'Sleeping bag')
        ->assertSet('parentName', 'Garage');
});

it('resolves the chosen destination to its item id', function () {
    Native::visit('/inventory/create')
        ->set('parentName', 'Tool chest')
        ->assertSet('parentId', 7)
        ->set('parentName', 'Top level')
        ->assertSet('parentId', null);
});

it('resolves the chosen type from the selector index', function () {
    Native::visit('/inventory/create')
        ->assertSet('type', ItemType::Location)
        ->changeTab('create-item-type', 2)
        ->assertSet('typeIndex', 2)
        ->assertSet('type', ItemType::Item);
});

it('shows the camera control as a platform icon next to the library link', function (string $platform, string $iconName) {
    Native::visit('/inventory/create', platform: $platform)
        ->assertElement('icon', fn (array $node): bool => ($node['props']['name'] ?? null) === $iconName)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-photo-camera')
        ->assertSee('View Library');
})->with([
    'ios' => ['ios', 'camera'],
    'android' => ['android', 'photo_camera'],
]);

it('asks the camera for a photo', function () {
    Native::fakeBridge();

    Native::visit('/inventory/create')
        ->tap('create-item-photo-camera')
        ->assertNativeCalled('Camera.GetPhoto');
});

it('asks the gallery for a single image', function () {
    Native::fakeBridge();

    Native::visit('/inventory/create')
        ->tap('create-item-photo-library')
        ->assertNativeCalled('Camera.PickMedia', fn (array $params): bool => ($params['mediaType'] ?? null) === 'image'
            && ($params['multiple'] ?? null) === false);
});

it('previews a captured photo and lets it be removed', function () {
    Native::visit('/inventory/create')
        ->assertMissingElement('image')
        ->emitNative(PhotoTaken::class, ['path' => '/tmp/captured.jpg'])
        ->assertSet('photoPath', '/tmp/captured.jpg')
        ->assertElement('image', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-photo'
            && ($node['props']['src'] ?? null) === '/tmp/captured.jpg')
        ->tap('create-item-photo-remove')
        ->assertSet('photoPath', null)
        ->assertMissingElement('image');
});

it('takes the first file a gallery selection hands back', function () {
    Native::visit('/inventory/create')
        ->emitNative(MediaSelected::class, [
            'success' => true,
            'files' => [
                ['path' => '/tmp/gallery_selected_1.jpg', 'mimeType' => 'image/jpeg'],
                ['path' => '/tmp/gallery_selected_2.jpg', 'mimeType' => 'image/jpeg'],
            ],
        ])
        ->assertSet('photoPath', '/tmp/gallery_selected_1.jpg');
});

it('keeps the current photo when a gallery selection fails or is cancelled', function () {
    Native::visit('/inventory/create')
        ->set('photoPath', '/tmp/captured.jpg')
        ->emitNative(MediaSelected::class, ['success' => false, 'files' => []])
        ->assertSet('photoPath', '/tmp/captured.jpg');
});

it('gives every tappable control at least a 48dp touch target', function () {
    $harness = Native::visit('/inventory/create')
        ->tap('metadata-add')
        ->set('photoPath', '/tmp/captured.jpg');

    $undersized = [];

    $walk = function (array $node) use (&$walk, &$undersized): void {
        $type = $node['type'] ?? '';

        if (in_array($type, ['button', 'pressable'], true)) {
            // Buttons without an explicit height fall back to the renderer's
            // size metrics: only `lg` clears 48dp (sm is 32, md is 36).
            $height = $node['layout']['height'] ?? null;
            $tallEnough = $height === null
                ? ($node['props']['size'] ?? null) === 'lg'
                : $height >= 48;

            if (! $tallEnough) {
                $undersized[] = ($node['ref'] ?? $type).' ('.($height ?? 'size='.($node['props']['size'] ?? '?')).')';
            }
        }

        foreach ($node['children'] ?? [] as $child) {
            $walk($child);
        }
    };

    $walk($harness->tree());

    expect($undersized)->toBe([]);
});

it('adds, fills, and removes metadata fields', function () {
    Native::visit('/inventory/create')
        ->assertMissingElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'metadata-key-0')
        ->tap('metadata-add')
        ->tap('metadata-add')
        ->input('metadata-key-0', 'capacity')
        ->input('metadata-value-0', '4 people')
        ->input('metadata-key-1', 'brand')
        ->input('metadata-value-1', 'REI')
        ->assertSet('metadata', [
            ['key' => 'capacity', 'value' => '4 people'],
            ['key' => 'brand', 'value' => 'REI'],
        ])
        ->tap('metadata-remove-0')
        ->assertSet('metadata', [['key' => 'brand', 'value' => 'REI']])
        ->assertMissingElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'metadata-key-1');
});

it('collapses the metadata pairs into the shape the item model stores', function () {
    Native::visit('/inventory/create')
        ->set('metadata', [
            ['key' => ' capacity ', 'value' => '4 people'],
            ['key' => '', 'value' => 'orphaned'],
        ])
        ->assertSet('metadataMap', ['capacity' => '4 people'])
        ->set('metadata', [])
        ->assertSet('metadataMap', null);
});

it('refuses to save metadata with duplicate keys', function () {
    Native::visit('/inventory/create')
        ->input('create-item-name', 'Sleeping bag')
        ->set('metadata', [
            ['key' => 'size', 'value' => 'Long'],
            ['key' => 'size', 'value' => 'Regular'],
        ])
        ->tap('create-item-submit')
        ->assertNoNavigation()
        ->assertSee('Metadata keys must be unique.');
});

it('refuses to save a metadata key with no value', function () {
    Native::visit('/inventory/create')
        ->input('create-item-name', 'Sleeping bag')
        ->set('metadata', [['key' => 'size', 'value' => '']])
        ->tap('create-item-submit')
        ->assertNoNavigation()
        ->assertSee('Give every metadata field a value.');
});

it('refuses to save an item without a name', function () {
    Native::visit('/inventory/create')
        ->input('create-item-name', '   ')
        ->tap('create-item-submit')
        ->assertNoNavigation()
        ->assertSee('Give the item a name.');
});

it('keeps unsaved items on the form when the API rejects the save', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 401)]);

    Native::visit('/inventory/create')
        ->input('create-item-name', 'Sleeping bag')
        ->tap('create-item-submit')
        ->assertNoNavigation()
        ->assertSet('error', 'Your session expired. Log in again before saving.');
});
