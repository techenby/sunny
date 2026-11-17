<?php

use App\Actions\Inventory\DuplicateItem;
use App\Models\Item;
use App\Models\ItemPhoto;
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

test('it copies every photo to new files for each duplicate, keeping their order', function () {
    Storage::fake();

    $item = Item::factory()->create(['name' => 'Guitar']);
    $front = "teams/{$item->team_id}/items/guitar-front.jpg";
    $back = "teams/{$item->team_id}/items/guitar-back.jpg";
    Storage::put($front, 'front');
    Storage::put($back, 'back');
    ItemPhoto::factory()->for($item)->create(['path' => $back, 'position' => 1]);
    ItemPhoto::factory()->for($item)->create(['path' => $front, 'position' => 0]);

    $copies = (new DuplicateItem)->handle($item, 2);

    $copies->each(function (Item $copy) {
        $photos = $copy->photos()->get();

        expect($photos)->toHaveCount(2)
            ->and($photos->pluck('position')->all())->toBe([0, 1])
            ->and(Storage::get($photos[0]->path))->toBe('front')
            ->and(Storage::get($photos[1]->path))->toBe('back');
    });

    expect(ItemPhoto::query()->pluck('path')->unique())->toHaveCount(6);
    Storage::assertExists([$front, $back]);
});

test('it copies the thumbnail to a new file for each duplicate', function () {
    Storage::fake();

    $item = Item::factory()->create(['name' => 'Guitar']);
    $path = "teams/{$item->team_id}/items/guitar-{$item->id}.jpg";
    $thumb = "teams/{$item->team_id}/items/thumbs/guitar-{$item->id}-abcdefgh.jpg";
    Storage::put($path, 'photo-contents');
    Storage::put($thumb, 'thumb-contents');
    ItemPhoto::factory()->for($item)->create(['path' => $path, 'thumb_path' => $thumb]);

    $copies = (new DuplicateItem)->handle($item, 2);

    $copies->each(function (Item $copy) use ($thumb) {
        $copyThumb = $copy->photos()->first()->thumb_path;

        expect($copyThumb)->not->toBe($thumb);
        expect(Storage::get($copyThumb))->toBe('thumb-contents');
    });

    expect($copies->map(fn (Item $copy) => $copy->photos()->first()->thumb_path)->unique())->toHaveCount(2);
});

test('a failure part-way leaves no copies and no copied photo files', function () {
    Storage::fake();
    $item = Item::factory()->create(['name' => 'Guitar']);
    $photo = "teams/{$item->team_id}/items/guitar.jpg";
    $thumb = "teams/{$item->team_id}/items/thumbs/guitar-abcd1234.jpg";
    Storage::put($photo, 'photo');
    Storage::put($thumb, 'thumb');
    ItemPhoto::factory()->for($item)->create(['path' => $photo, 'thumb_path' => $thumb]);

    $created = 0;
    ItemPhoto::creating(function () use (&$created): void {
        throw_if(++$created === 2, RuntimeException::class, 'Disk full');
    });

    expect(fn () => (new DuplicateItem)->handle($item, 3))->toThrow(RuntimeException::class, 'Disk full');

    expect(Item::count())->toBe(1)
        ->and(ItemPhoto::count())->toBe(1)
        ->and(Storage::allFiles())->toEqualCanonicalizing([$photo, $thumb]);
});
