<?php

use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Support\Facades\Storage;

test('an item without photos has no photo or thumbnail url', function () {
    $item = Item::factory()->create();

    expect($item->photo_url)->toBeNull()
        ->and($item->thumb_url)->toBeNull()
        ->and($item->load('photos')->thumb_url)->toBeNull();
});

test('the primary photo gives the item its photo and thumbnail urls', function () {
    Storage::fake();
    $item = Item::factory()->create();
    ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/back.jpg', 'thumb_path' => 'teams/1/items/thumbs/back.jpg', 'position' => 1]);
    ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/front.jpg', 'thumb_path' => 'teams/1/items/thumbs/front.jpg', 'position' => 0]);

    expect($item->photo_url)->toContain('items/front.jpg')
        ->and($item->thumb_url)->toContain('thumbs/front.jpg')
        ->and($item->fresh()->load('photos')->thumb_url)->toContain('thumbs/front.jpg');
});

test('the thumbnail url falls back to the photo when there is no thumbnail', function () {
    Storage::fake();
    $item = Item::factory()->withPhoto('teams/1/items/front.jpg')->create();

    expect($item->thumb_url)->toContain('items/front.jpg');
});
