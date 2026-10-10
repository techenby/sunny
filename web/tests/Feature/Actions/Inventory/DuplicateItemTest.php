<?php

use App\Actions\Inventory\DuplicateItem;
use App\Models\Item;
use Illuminate\Support\Facades\Storage;

test('it creates the requested number of copies', function () {
    $parent = Item::factory()->location()->create();
    $item = Item::factory()->for($parent->team)->childOf($parent)->create([
        'name' => 'Hammer',
        'metadata' => ['brand' => 'Stanley'],
    ]);

    $copies = (new DuplicateItem)->handle($item, 3);

    expect($copies)->toHaveCount(3);

    $copies->each(function (Item $copy) use ($item) {
        expect($copy)
            ->id->not->toBe($item->id)
            ->name->toBe('Hammer')
            ->team_id->toBe($item->team_id)
            ->parent_id->toBe($item->parent_id)
            ->type->toBe($item->type)
            ->metadata->toBe(['brand' => 'Stanley']);
    });
});

test('it defaults to a single copy', function () {
    $item = Item::factory()->create();

    $copies = (new DuplicateItem)->handle($item);

    expect($copies)->toHaveCount(1);
    expect(Item::count())->toBe(2);
});

test('it copies the photo to a new file for each duplicate', function () {
    Storage::fake();

    $item = Item::factory()->create(['name' => 'Guitar']);
    $path = "teams/{$item->team_id}/items/guitar-{$item->id}.jpg";
    Storage::put($path, 'photo-contents');
    $item->update(['photo_path' => $path]);

    $copies = (new DuplicateItem)->handle($item, 2);

    $copies->each(function (Item $copy) use ($path) {
        expect($copy->photo_path)->not->toBe($path);
        Storage::assertExists($copy->photo_path);
    });

    expect($copies->pluck('photo_path')->unique())->toHaveCount(2);
    Storage::assertExists($path);
});

test('it copies the thumbnail to a new file for each duplicate', function () {
    Storage::fake();

    $item = Item::factory()->create(['name' => 'Guitar']);
    $path = "teams/{$item->team_id}/items/guitar-{$item->id}.jpg";
    $thumb = "teams/{$item->team_id}/items/thumbs/guitar-{$item->id}-abcdefgh.jpg";
    Storage::put($path, 'photo-contents');
    Storage::put($thumb, 'thumb-contents');
    $item->update(['photo_path' => $path, 'thumb_path' => $thumb]);

    $copies = (new DuplicateItem)->handle($item, 2);

    $copies->each(function (Item $copy) use ($thumb) {
        expect($copy->thumb_path)->not->toBe($thumb);
        expect(Storage::get($copy->thumb_path))->toBe('thumb-contents');
    });

    expect($copies->pluck('thumb_path')->unique())->toHaveCount(2);
});
