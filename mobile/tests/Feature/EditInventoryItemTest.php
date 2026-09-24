<?php

use App\Enums\ItemType;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\NativeComponents\EditInventoryItem;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;

beforeEach(fn () => seedSunnyData());

it('opens the edit screen from the item', function () {
    Native::visit('/inventory/8')
        ->tap('edit-item')
        ->assertNavigatedTo('/inventory/8/edit')
        ->follow()
        ->assertScreen(EditInventoryItem::class);
});

it('titles the screen with the item being edited', function () {
    Native::visit('/inventory/8/edit')
        ->assertNavTitle('Edit Cordless drill');
});

it('prefills the form from the item', function () {
    Native::visit('/inventory/8/edit')
        ->assertSet('name', 'Cordless drill')
        ->assertSet('type', ItemType::Item)
        ->assertSet('parentName', 'Tool chest')
        ->assertSet('parentId', 7)
        ->assertSet('metadata', [
            ['key' => 'brand', 'value' => 'DeWalt'],
            ['key' => 'model', 'value' => 'DCD771'],
        ])
        ->assertSee('Update item');
});

it('prefills a top-level item with no metadata', function () {
    Native::visit('/inventory/6/edit')
        ->assertSet('type', ItemType::Location)
        ->assertSet('parentName', 'Top level')
        ->assertSet('metadata', []);
});

it('does not offer the item or anything inside it as a destination', function () {
    Native::visit('/inventory/6/edit')
        ->assertElement('select', fn (array $node): bool => ($node['props']['options'] ?? null) === [
            'Top level', 'Basement', 'Basmati rice', 'Canned tomatoes', 'Holiday decorations', 'Kitchen',
            'Olive oil', 'Ornaments', 'Pantry', 'Spare light bulbs', 'String lights',
        ]);
});

it('edits a field and keeps the rest intact', function () {
    Native::visit('/inventory/8/edit')
        ->input('edit-item-name', 'Hammer drill')
        ->select('edit-item-parent', 'Garage')
        ->assertSet('name', 'Hammer drill')
        ->assertSet('parentId', 6)
        ->assertSet('metadataMap', ['brand' => 'DeWalt', 'model' => 'DCD771']);
});

it('refuses to update an item without a name', function () {
    Native::visit('/inventory/8/edit')
        ->input('edit-item-name', '   ')
        ->tap('edit-item-submit')
        ->assertNoNavigation()
        ->assertSee('Give the item a name.');
});

it('keeps unsaved item edits on the form when the API rejects the save', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 401)]);

    Native::visit('/inventory/8/edit')
        ->tap('edit-item-submit')
        ->assertNoNavigation()
        ->assertSet('error', 'Your session expired. Log in again before saving.');
});

it('explains when the item does not exist', function () {
    Native::visit('/inventory/999/edit')
        ->assertNavTitle('Edit item')
        ->assertSee('This item could not be found.')
        ->assertMissingElement('outlined_text_input');
});

it('offers no edit action on an item that does not exist', function () {
    Native::visit('/inventory/999')
        ->assertMissingElement('top_bar_action');
});
