<?php

use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('existing item photos become the primary photo and go back on rollback', function () {
    $migration = require database_path('migrations/2026_11_16_190000_create_item_photos_table.php');
    $withPhoto = Item::factory()->create();
    $withoutThumb = Item::factory()->create();
    $withoutPhoto = Item::factory()->create();
    $trashed = Item::factory()->create();

    $migration->down();

    DB::table('items')->where('id', $withPhoto->id)->update(['photo_path' => 'teams/1/items/tent.jpg', 'thumb_path' => 'teams/1/items/thumbs/tent.jpg']);
    DB::table('items')->where('id', $withoutThumb->id)->update(['photo_path' => 'teams/1/items/lamp.jpg']);
    DB::table('items')->where('id', $trashed->id)->update(['photo_path' => 'teams/1/items/old.jpg', 'deleted_at' => now()]);

    $migration->up();

    expect(Schema::hasColumns('items', ['photo_path']))->toBeFalse()
        ->and(Schema::hasColumns('items', ['thumb_path']))->toBeFalse()
        ->and($withPhoto->photos()->sole())
        ->path->toBe('teams/1/items/tent.jpg')
        ->thumb_path->toBe('teams/1/items/thumbs/tent.jpg')
        ->position->toBe(0)
        ->and($withoutThumb->photos()->sole())
        ->path->toBe('teams/1/items/lamp.jpg')
        ->thumb_path->toBeNull()
        ->and($trashed->photos()->sole()->path)->toBe('teams/1/items/old.jpg')
        ->and($withoutPhoto->photos()->count())->toBe(0);

    ItemPhoto::factory()->for($withPhoto)->create(['path' => 'teams/1/items/tent-back.jpg', 'position' => 1]);

    $migration->down();

    expect(DB::table('items')->find($withPhoto->id))
        ->photo_path->toBe('teams/1/items/tent.jpg')
        ->thumb_path->toBe('teams/1/items/thumbs/tent.jpg')
        ->and(DB::table('items')->find($withoutPhoto->id)->photo_path)->toBeNull()
        ->and(Schema::hasTable('item_photos'))->toBeFalse();

    $migration->up();
});
