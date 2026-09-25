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

test('qr code for a leaf item encodes the show url', function () {
    $item = Item::factory()->create();

    $qrCode = resolve(GenerateItemQrCode::class)->handle($item);

    expect($qrCode['url'])->toBe(route('inventory.show', ['current_team' => $item->team, 'item' => $item]));
    expect($qrCode['name'])->toBe($item->name);
});

test('qr code for a parent item encodes the index url with parentId', function () {
    $parent = Item::factory()->create();
    Item::factory()->for($parent->team)->create(['parent_id' => $parent->id]);

    $qrCode = resolve(GenerateItemQrCode::class)->handle($parent);

    expect($qrCode['url'])->toBe(route('inventory.index', ['current_team' => $parent->team, 'parentId' => $parent->id]));
    expect($qrCode['name'])->toBe($parent->name);
});
