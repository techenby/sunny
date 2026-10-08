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

test('qr code encodes the short item link', function () {
    $item = Item::factory()->create();

    $qrCode = resolve(GenerateItemQrCode::class)->handle($item);

    expect($qrCode['url'])->toBe(route('inventory.link', $item));
    expect($qrCode['name'])->toBe($item->name);
});
