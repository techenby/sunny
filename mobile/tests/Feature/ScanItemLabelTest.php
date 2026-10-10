<?php

use App\NativeComponents\OpenItemLink;
use Native\Mobile\Events\Scanner\CodeScanned;
use Native\Mobile\Testing\Native;
use Sunny\ItemScanner\Events\ScreenUncovered;

beforeEach(fn () => seedSunnyData());

it('opens the scanner from the inventory scan menu', function () {
    Native::visit('/inventory')
        ->tap('Scan label')
        ->assertScanRequested('Scan a Sunny label');
});

it('opens the item on a scanned label once the scanner has closed', function (string $url) {
    Native::visit('/inventory')
        ->emitNative(CodeScanned::class, ['data' => $url, 'format' => 'qr', 'id' => 'item-code'])
        ->assertNativeCalled('ItemScanner.WhenUncovered', fn (array $params): bool => $params === ['id' => 'item-code'])
        ->assertNoNavigation()
        ->emitNative(ScreenUncovered::class, ['id' => 'item-code'])
        ->assertNavigatedTo('/i/7');
})->with([
    'short link' => 'https://sunny.example/i/7',
    'item page' => 'https://sunny.example/family/inventory/7',
    'contents page' => 'https://sunny.example/family/inventory?parentId=7',
]);

it('rejects codes that are not Sunny labels', function (string $code) {
    Native::visit('/inventory')
        ->emitNative(CodeScanned::class, ['data' => $code, 'format' => 'qr', 'id' => 'item-code'])
        ->assertNoNavigation()
        ->assertNativeCalled('Dialog.Toast', fn (array $params): bool => $params['message'] === 'That code isn’t a Sunny label.');
})->with([
    'another site' => 'https://example.com/i/7',
    'a lookalike host' => 'https://sunny.example.evil.com/i/7',
    'another Sunny page' => 'https://sunny.example/family/recipes/7',
    'plain text' => 'hello',
]);

it('opens the item straight away when the scanner closing can’t be watched', function () {
    Native::fakeBridge()->withoutCapability('ItemScanner.WhenUncovered');

    Native::visit('/inventory')
        ->emitNative(CodeScanned::class, ['data' => 'https://sunny.example/i/7', 'format' => 'qr', 'id' => 'item-code'])
        ->assertNavigatedTo('/i/7');
});

it('ignores the scanner closing when no label was scanned', function () {
    Native::visit('/inventory')
        ->emitNative(ScreenUncovered::class, ['id' => 'item-code'])
        ->assertNoNavigation();
});

it('ignores scans started by other screens', function () {
    Native::visit('/inventory')
        ->emitNative(CodeScanned::class, ['data' => 'https://sunny.example/i/7', 'format' => 'qr', 'id' => 'something-else'])
        ->assertNoNavigation();
});

it('maps scanned urls to the item link route', function () {
    expect(OpenItemLink::pathFor('https://sunny.example/i/7?utm=x'))->toBe('/i/7')
        ->and(OpenItemLink::pathFor('https://sunny.example/family/inventory?parentId=abc'))->toBeNull()
        ->and(OpenItemLink::pathFor('https://sunny.example/family/inventory'))->toBeNull();
});
