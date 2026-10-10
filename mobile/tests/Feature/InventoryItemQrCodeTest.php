<?php

use App\NativeComponents\InventoryItemQrCode;
use Native\Mobile\Testing\Native;

beforeEach(fn () => seedSunnyData());

it('opens the QR code from an item', function () {
    Native::visit('/inventory/8')
        ->tap('item-qr-code')
        ->assertNavigatedTo('/inventory/8/qr-code');
});

it('shows a QR code linking to the item', function () {
    $path = storage_path('app/qr-codes/cordless-drill-8.png');

    Native::visit('/inventory/8/qr-code')
        ->assertScreen(InventoryItemQrCode::class)
        ->assertSee('Cordless drill')
        ->assertSee('https://sunny.example/i/8')
        ->assertSet('path', $path);

    expect(getimagesize($path))->toMatchArray([0 => 528, 1 => 528, 'mime' => 'image/png']);
});

it('shares the QR code image for printing', function () {
    Native::visit('/inventory/8/qr-code')
        ->tap('item-qr-code-share')
        ->assertSharedFile(storage_path('app/qr-codes/cordless-drill-8.png'));
});

it('shares from the top bar', function () {
    Native::visit('/inventory/8/qr-code')
        ->tap('share-qr-code')
        ->assertSharedFile(storage_path('app/qr-codes/cordless-drill-8.png'));
});

it('copies the item name', function () {
    Native::fakeBridge()->withClipboard();

    Native::visit('/inventory/8/qr-code')
        ->assertNothingCopied()
        ->tap('item-qr-code-copy-name')
        ->assertCopied('Cordless drill')
        ->assertSet('copied', 'name')
        ->assertSet('error', '');
});

it('copies the item link', function () {
    Native::fakeBridge()->withClipboard();

    Native::visit('/inventory/8/qr-code')
        ->tap('item-qr-code-copy-name')
        ->tap('item-qr-code-copy-url')
        ->assertCopied('https://sunny.example/i/8')
        ->assertSet('copied', 'url')
        ->assertSet('error', '');
});

it('explains when the link could not be copied', function () {
    Native::visit('/inventory/8/qr-code')
        ->tap('item-qr-code-copy-url')
        ->assertSet('copied', null)
        ->assertSee('Unable to copy the link. Please try again.');
});

it('does not share before asked', function () {
    Native::visit('/inventory/8/qr-code')->assertNothingShared();
});

it('explains when the item is missing', function () {
    Native::visit('/inventory/999/qr-code')
        ->assertSee('This item could not be found.')
        ->assertDontSee('Share for printing');
});
