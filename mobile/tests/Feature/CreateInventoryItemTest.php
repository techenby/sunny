<?php

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Models\PendingWrite;
use App\NativeComponents\CreateInventoryItem;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Events\Gallery\MediaSelected;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;

beforeEach(fn () => seedSunnyData());

it('opens the create screen from the inventory add menu', function () {
    Native::visit('/inventory')
        ->tap('Add one thing')
        ->assertNavigatedTo('/inventory/create')
        ->follow()
        ->assertScreen(CreateInventoryItem::class)
        ->assertNavTitle('Add something');
});

it('renders the form as plain questions', function () {
    Native::visit('/inventory/create')
        ->assertSee('What is it called?')
        ->assertSee('Where does it go?')
        ->assertSee('Not in a place yet')
        ->assertSee('What kind is it?')
        ->assertSee('Save')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-type-item')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-type-bin')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-type-location')
        ->assertAccessible();
});

it('names the place it will save to on the button', function () {
    Native::visit('/inventory/create', data: ['parent' => 7])
        ->assertSee('Save to Tool chest');
});

it('offers every item as a destination, grouped by location then bin then item', function () {
    $screen = Native::visit('/inventory/create');

    expect(array_map(
        fn (array $choice): array => [$choice['name'], $choice['depth'], $choice['path']],
        $screen->get('parentChoices'),
    ))->toBe([
        ['Basement', 0, null],
        ['Holiday decorations', 1, 'Basement'],
        ['Ornaments', 2, 'Basement › Holiday decorations'],
        ['String lights', 2, 'Basement › Holiday decorations'],
        ['Spare light bulbs', 1, 'Basement'],
        ['Garage', 0, null],
        ['Tool chest', 1, 'Garage'],
        ['Cordless drill', 2, 'Garage › Tool chest'],
        ['Tape measure', 2, 'Garage › Tool chest'],
        ['Camping tent', 1, 'Garage'],
        ['Kitchen', 0, null],
        ['Pantry', 1, 'Kitchen'],
        ['Basmati rice', 2, 'Kitchen › Pantry'],
        ['Canned tomatoes', 2, 'Kitchen › Pantry'],
        ['Olive oil', 2, 'Kitchen › Pantry'],
    ])
        ->and(array_column($screen->get('parentPickerRows'), 'name'))
        ->toBe(['Basement', 'Garage', 'Kitchen']);
});

it('only builds the destination rows while the picker is open', function () {
    Native::visit('/inventory/create')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-parent-6')
        ->tap('create-item-parent')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-parent-6');
});

it('browses into a location and chooses it', function () {
    Native::visit('/inventory/create')
        ->input('create-item-name', 'Sleeping bag')
        ->tap('create-item-parent')
        ->assertSet('showParentPicker', true)
        ->tap('create-item-parent-6')
        ->assertSet('parentBrowseId', 6)
        ->assertSet('showParentPicker', true)
        ->assertSee('Put it in Garage')
        ->tap('create-item-parent-choose')
        ->assertSet('name', 'Sleeping bag')
        ->assertSet('parentId', 6)
        ->assertSet('showParentPicker', false)
        ->assertSee('Garage');
});

it('opens storage, lists loose things on one line, and only chooses with the button', function () {
    $screen = Native::visit('/inventory/create')
        ->tap('create-item-parent')
        ->tap('create-item-parent-6');

    expect(array_column($screen->get('parentPickerRows'), 'name'))->toBe(['Tool chest', 'Camping tent']);

    $screen->assertSee('Also here: Camping tent')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-parent-10')
        ->tap('create-item-parent-7')
        ->assertSet('parentBrowseId', 7)
        ->assertSet('parentId', null)
        ->assertSee('Here: Cordless drill, Tape measure')
        ->tap('create-item-parent-choose')
        ->assertSet('parentId', 7)
        ->assertSee('in Garage');
});

it('reopens beside the choice and walks back up with the breadcrumbs', function () {
    Native::visit('/inventory/create')
        ->set('parentId', 7)
        ->tap('create-item-parent')
        ->assertSet('parentBrowseId', 6)
        ->tap('create-item-parent-7')
        ->tap('create-item-parent-crumb-6')
        ->assertSet('parentBrowseId', 6)
        ->tap('create-item-parent-crumb-home')
        ->assertSet('parentBrowseId', null);
});

it('moves the item back to the top level', function () {
    Native::visit('/inventory/create')
        ->set('parentId', 7)
        ->tap('create-item-parent')
        ->tap('create-item-parent-crumb-home')
        ->assertSee('Don’t put it in a place yet')
        ->tap('create-item-parent-choose')
        ->assertSet('parentId', null);
});

it('searches destinations at any depth and shows where each match lives', function () {
    $screen = Native::visit('/inventory/create')
        ->tap('create-item-parent')
        ->set('parentSearch', 'ch');

    expect(array_map(fn (array $choice): array => [$choice['name'], $choice['path']], $screen->get('parentPickerRows')))
        ->toBe([['Tool chest', 'Garage'], ['Kitchen', null]]);

    $screen->assertSee('In Garage')
        ->assertMissingElement('button', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-parent-choose')
        ->tap('create-item-parent-7')
        ->assertSet('parentId', 7)
        ->tap('create-item-parent')
        ->set('parentSearch', 'sleeping bag')
        ->assertSee('Nothing called “sleeping bag”');
});

it('closes the destination picker when dismissed without changing the choice', function () {
    Native::visit('/inventory/create')
        ->tap('create-item-parent')
        ->dismissSheet('create-item-parent-picker')
        ->assertSet('showParentPicker', false)
        ->assertSet('parentId', null);
});

it('calls the top level Home when adding a place', function () {
    Native::visit('/inventory/create')
        ->tap('create-item-type-location')
        ->assertDontSee('Not in a place yet')
        ->tap('create-item-parent')
        ->assertSee('Put it with the places in your home');
});

it('starts as a thing and switches kind with a tap', function () {
    Native::visit('/inventory/create')
        ->assertSet('type', ItemType::Item)
        ->tap('create-item-type-location')
        ->assertSet('type', ItemType::Location)
        ->tap('create-item-type-bin')
        ->assertSet('type', ItemType::Bin);
});

it('labels the camera and library buttons', function (string $platform, string $iconName) {
    Native::visit('/inventory/create', platform: $platform)
        ->assertElement('icon', fn (array $node): bool => ($node['props']['name'] ?? null) === $iconName)
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-item-photo-camera')
        ->assertSee('Take photo')
        ->assertSee('Choose from photos');
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
        ->set('photoPath', '/tmp/captured.jpg')
        ->tap('create-item-parent');

    $undersized = [];

    $walk = function (array $node) use (&$walk, &$undersized): void {
        $type = $node['type'] ?? '';

        if (in_array($type, ['button', 'pressable'], true)) {
            // Buttons without an explicit height fall back to the renderer's
            // size metrics: only `lg` clears 48dp (sm is 32, md is 36). A
            // minimum height counts too, so controls can grow with Dynamic Type.
            $height = $node['layout']['height'] ?? $node['layout']['min_height'] ?? null;
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

it('ignores a late metadata edit for a row that was already removed', function () {
    Native::visit('/inventory/create')
        ->tap('metadata-add')
        ->input('metadata-key-0', 'brand')
        ->tap('metadata-remove-0')
        ->call('setMetadataKey', 0, 'stale')
        ->call('setMetadataValue', 0, 'stale')
        ->assertSet('metadata', [])
        ->set('name', 'Tent')
        ->tap('create-item-submit')
        ->assertDontSee('Undefined array key');
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
        ->assertSee('Each detail needs a different name.');
});

it('refuses to save a metadata key with no value', function () {
    Native::visit('/inventory/create')
        ->input('create-item-name', 'Sleeping bag')
        ->set('metadata', [['key' => 'size', 'value' => '']])
        ->tap('create-item-submit')
        ->assertNoNavigation()
        ->assertSee('Fill in every detail, or remove the empty ones.');
});

it('refuses to save an item without a name', function () {
    Native::visit('/inventory/create')
        ->input('create-item-name', '   ')
        ->tap('create-item-submit')
        ->assertNoNavigation()
        ->assertSee('Give it a name.');
});

it('keeps the new item queued on the phone when Sunny rejects the session', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 401)]);

    Native::visit('/inventory/create')
        ->input('create-item-name', 'Sleeping bag')
        ->tap('create-item-submit')
        ->assertSet('error', '')
        ->assertReplacedWith('/inventory/-1');

    expect(PendingWrite::sole()->error)->toBeNull();
});
