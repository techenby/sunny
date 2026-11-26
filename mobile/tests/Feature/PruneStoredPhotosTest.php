<?php

use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyThumbnails;
use App\Models\Item;
use Illuminate\Support\Facades\File;

beforeEach(function (): void {
    foreach ([SunnyOutbox::photoDirectory(), SunnyThumbnails::directory()] as $directory) {
        File::deleteDirectory($directory);
        File::ensureDirectoryExists($directory);
    }

    $this->file = function (string $directory, string $name, int $minutesAgo): string {
        $path = $directory.'/'.$name;
        File::put($path, 'photo');
        touch($path, now()->subMinutes($minutesAgo)->getTimestamp());

        return $path;
    };
});

afterEach(function (): void {
    File::deleteDirectory(SunnyOutbox::photoDirectory());
    File::deleteDirectory(SunnyThumbnails::directory());
});

it('keeps a photo copied in during the last minute, before its write is saved', function (): void {
    $saving = ($this->file)(SunnyOutbox::photoDirectory(), 'saving.jpg', 0);
    $leftOver = ($this->file)(SunnyOutbox::photoDirectory(), 'left-over.jpg', 2);

    app(SunnyOutbox::class)->prunePhotos();

    expect($saving)->toBeFile()
        ->and($leftOver)->not->toBeFile();
});

it('keeps a thumbnail downloaded during the last minute, before its record points at it', function (): void {
    $downloading = ($this->file)(SunnyThumbnails::directory(), 'downloading.jpg', 0);
    $leftOver = ($this->file)(SunnyThumbnails::directory(), 'left-over.jpg', 2);

    app(SunnyThumbnails::class)->prune();

    expect($downloading)->toBeFile()
        ->and($leftOver)->not->toBeFile();
});

it('removes every unused photo and thumbnail when the phone is cleared, new ones too', function (): void {
    $photo = ($this->file)(SunnyOutbox::photoDirectory(), 'new.jpg', 0);
    $thumbnail = ($this->file)(SunnyThumbnails::directory(), 'new.jpg', 0);

    app(SunnyStore::class)->clear();

    expect($photo)->not->toBeFile()
        ->and($thumbnail)->not->toBeFile();
});

it('skips a file that another prune deleted after listing it', function (string $files): void {
    $directory = $files === 'outbox' ? SunnyOutbox::photoDirectory() : SunnyThumbnails::directory();
    $gone = $directory.'/gone.jpg';
    $leftOver = ($this->file)($directory, 'left-over.jpg', 2);
    File::partialMock()->shouldReceive('glob')->andReturn([$gone, $leftOver]);

    $files === 'outbox' ? app(SunnyOutbox::class)->prunePhotos() : app(SunnyThumbnails::class)->prune();

    expect($gone)->not->toBeFile()
        ->and($leftOver)->not->toBeFile();
})->with(['outbox', 'thumbnails']);

it('keeps an older thumbnail that a refresh is about to reuse', function (): void {
    seedSunnyData();
    $url = 'https://cdn.sunny.example/thumbs/kitchen.jpg?signature=new';
    $reused = ($this->file)(SunnyThumbnails::directory(), basename(SunnyThumbnails::pathFor($url)), 10);
    Item::query()->firstOrFail()->update(['thumb_url' => $url, 'thumb_path' => null]);

    app(SunnyThumbnails::class)->prune();

    expect($reused)->toBeFile();
});
