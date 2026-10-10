<?php

use App\Actions\Photos\CreateThumbnail;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

test('it shrinks a photo to fit within 300 pixels as a jpeg', function () {
    Storage::fake();
    Storage::put('teams/1/items/hammer-4.png', UploadedFile::fake()->image('hammer.png', 1200, 800)->get());

    $path = (new CreateThumbnail)->handle('teams/1/items/hammer-4.png');

    expect($path)->toMatch('#^teams/1/items/thumbs/hammer-4-[a-z0-9]{8}\.jpg$#');
    expect(getimagesizefromstring(Storage::get($path)))
        ->{0}->toBe(300)
        ->{1}->toBe(200)
        ->mime->toBe('image/jpeg');
});

test('it keeps a small photo at its own size', function () {
    Storage::fake();
    Storage::put('teams/1/recipes/cake.jpg', UploadedFile::fake()->image('cake.jpg', 120, 90)->get());

    $path = (new CreateThumbnail)->handle('teams/1/recipes/cake.jpg');

    expect(getimagesizefromstring(Storage::get($path)))
        ->{0}->toBe(120)
        ->{1}->toBe(90);
});

test('it gives each thumbnail of the same photo a new path', function () {
    Storage::fake();
    Storage::put('teams/1/recipes/cake.jpg', UploadedFile::fake()->image('cake.jpg')->get());

    $first = (new CreateThumbnail)->handle('teams/1/recipes/cake.jpg');
    $second = (new CreateThumbnail)->handle('teams/1/recipes/cake.jpg');

    expect($first)->not->toBe($second);
});

test('it turns a photo upright using its camera orientation', function () {
    Storage::fake();
    $image = imagecreatetruecolor(400, 200);
    imagefill($image, 0, 0, imagecolorallocate($image, 255, 0, 0));
    imagefilledrectangle($image, 0, 0, 99, 199, imagecolorallocate($image, 0, 0, 255));
    ob_start();
    imagejpeg($image);
    $jpeg = (string) ob_get_clean();
    $exif = "Exif\0\0MM\0\x2A\0\0\0\x08\0\x01\x01\x12\0\x03\0\0\0\x01\0\x06\0\0\0\0\0\0";
    Storage::put('teams/1/items/sideways.jpg', "\xFF\xD8\xFF\xE1" . pack('n', strlen($exif) + 2) . $exif . substr($jpeg, 2));

    $thumbnail = imagecreatefromstring(Storage::get((new CreateThumbnail)->handle('teams/1/items/sideways.jpg')));

    $top = imagecolorsforindex($thumbnail, imagecolorat($thumbnail, 75, 10));
    $bottom = imagecolorsforindex($thumbnail, imagecolorat($thumbnail, 75, 290));

    expect([imagesx($thumbnail), imagesy($thumbnail)])->toBe([150, 300])
        ->and($top['blue'])->toBeGreaterThan($top['red'])
        ->and($bottom['red'])->toBeGreaterThan($bottom['blue']);
});

test('it returns null when the photo cannot be read as an image', function () {
    Storage::fake();
    Storage::put('teams/1/items/notes.jpg', 'not an image');

    expect((new CreateThumbnail)->handle('teams/1/items/notes.jpg'))->toBeNull()
        ->and((new CreateThumbnail)->handle('teams/1/items/missing.jpg'))->toBeNull();
});

test('it copies a thumbnail alongside another photo', function () {
    Storage::fake();
    Storage::put('teams/1/items/thumbs/guitar-1-abcdefgh.jpg', 'thumb');

    $path = (new CreateThumbnail)->copy('teams/1/items/thumbs/guitar-1-abcdefgh.jpg', 'teams/1/items/guitar-2.jpg');

    expect($path)->toMatch('#^teams/1/items/thumbs/guitar-2-[a-z0-9]{8}\.jpg$#');
    expect(Storage::get($path))->toBe('thumb');
});
