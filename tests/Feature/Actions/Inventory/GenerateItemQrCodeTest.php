<?php

use App\Actions\Inventory\GenerateItemQrCode;
use App\Models\Item;

test('it generates an svg qr code for an item', function () {
    $item = Item::factory()->create();

    $qrCode = resolve(GenerateItemQrCode::class)->handle($item);

    expect($qrCode['svg'])
        ->toContain('<svg')
        ->toContain('</svg>');
});

test('qr code encodes the inventory url with the item id', function () {
    $item = Item::factory()->create();

    $qrCode = resolve(GenerateItemQrCode::class)->handle($item);

    expect($qrCode['svg'])->toBeString()->not->toBeEmpty();
});
