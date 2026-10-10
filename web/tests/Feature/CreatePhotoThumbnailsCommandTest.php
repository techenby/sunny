<?php

use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('it creates thumbnails for photos that do not have one', function () {
    Storage::fake();
    Storage::put('teams/1/items/hammer.jpg', UploadedFile::fake()->image('hammer.jpg')->get());
    Storage::put('teams/1/recipes/cake.jpg', UploadedFile::fake()->image('cake.jpg')->get());

    $item = Item::factory()->create(['photo_path' => 'teams/1/items/hammer.jpg']);
    $recipe = Recipe::factory()->create(['photo_path' => 'teams/1/recipes/cake.jpg']);
    $alreadyDone = Item::factory()->create(['photo_path' => 'teams/1/items/hammer.jpg', 'thumb_path' => 'teams/1/items/thumbs/existing.jpg']);
    $withoutPhoto = Item::factory()->create(['photo_path' => null]);

    $this->artisan('photos:thumbnails')
        ->expectsOutputToContain('2 thumbnail(s) created.')
        ->assertSuccessful();

    Storage::assertExists($item->fresh()->thumb_path);
    Storage::assertExists($recipe->fresh()->thumb_path);
    expect($alreadyDone->fresh()->thumb_path)->toBe('teams/1/items/thumbs/existing.jpg')
        ->and($withoutPhoto->fresh()->thumb_path)->toBeNull();
});

test('it reports photos it could not read', function () {
    Storage::fake();
    Storage::put('teams/1/items/broken.jpg', 'not an image');

    $item = Item::factory()->create(['photo_path' => 'teams/1/items/broken.jpg']);

    $this->artisan('photos:thumbnails')
        ->expectsOutputToContain('1 photo(s) could not be read.')
        ->assertSuccessful();

    expect($item->fresh()->thumb_path)->toBeNull();
});
