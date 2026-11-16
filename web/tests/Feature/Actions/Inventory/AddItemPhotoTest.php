<?php

use App\Actions\Inventory\AddItemPhoto;
use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;

test('it adds a photo to the end with a thumbnail', function () {
    Storage::fake();
    $item = Item::factory()->withPhoto('teams/1/items/front.jpg')->create(['name' => 'Tent']);

    $photo = (new AddItemPhoto)->handle($item, UploadedFile::fake()->image('back.jpg'));

    expect($photo->position)->toBe(1)
        ->and($photo->path)->toStartWith("teams/{$item->team_id}/items/tent-{$item->id}-");
    Storage::assertExists([$photo->path, $photo->thumb_path]);
});

test('it can put the new photo first', function () {
    Storage::fake();
    $item = Item::factory()->withPhoto('teams/1/items/front.jpg')->create();

    $photo = (new AddItemPhoto)->handle($item, UploadedFile::fake()->image('new.jpg'), position: 0);

    expect($item->photos()->pluck('id')->first())->toBe($photo->id)
        ->and($item->photos()->pluck('position')->all())->toBe([0, 1]);
});

test('it refuses an eleventh photo and keeps no files for it', function () {
    Storage::fake();
    $item = Item::factory()->create();
    ItemPhoto::factory()->for($item)->count(10)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

    expect(fn () => (new AddItemPhoto)->handle($item, UploadedFile::fake()->image('eleven.jpg')))
        ->toThrow(ValidationException::class, 'An item can have up to 10 photos.');

    expect($item->photos()->count())->toBe(10)
        ->and(Storage::allFiles())->toBe([]);
});
