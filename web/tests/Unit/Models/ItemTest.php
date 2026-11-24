<?php

use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

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

test('an item cannot be saved inside another team\'s item', function () {
    $item = Item::factory()->create();
    $elsewhere = Item::factory()->create();

    expect(fn () => $item->update(['parent_id' => $elsewhere->id]))->toThrow(ValidationException::class)
        ->and($item->fresh()->parent_id)->toBeNull();
});

test('descendants stop at a loop left in the data', function () {
    $shelf = Item::factory()->create();
    $bin = Item::factory()->for($shelf->team)->childOf($shelf)->create();
    Item::query()->whereKey($shelf->id)->update(['parent_id' => $bin->id]);

    expect($shelf->descendantIds()->sort()->values()->all())->toBe([$shelf->id, $bin->id]);
});

test('the database refuses a place in another team even when the model is skipped', function () {
    $item = Item::factory()->create();
    $elsewhere = Item::factory()->create();

    expect(fn () => Item::query()->whereKey($item->id)->update(['parent_id' => $elsewhere->id]))->toThrow(QueryException::class);
});

test('changing an item\'s place reads its team\'s row for the lock before looking for a loop', function () {
    $shelf = Item::factory()->create();
    $bin = Item::factory()->for($shelf->team)->create();
    $queries = collect();
    DB::listen(fn (QueryExecuted $query) => $queries->push($query->sql));

    $bin->update(['parent_id' => $shelf->id]);

    $lock = $queries->search(fn (string $sql): bool => str_contains($sql, 'from "teams"'));
    $check = $queries->search(fn (string $sql): bool => str_contains($sql, 'with recursive'));

    expect($lock)->not->toBeFalse()
        ->and($check)->toBeGreaterThan($lock)
        ->and($bin->fresh()->parent_id)->toBe($shelf->id);
});
