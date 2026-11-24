<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyThumbnails;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\PendingWrite;
use App\Models\Recipe;
use App\Models\ScanDraft;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Repositories\Body\MultipartBodyRepository;
use Sunny\ItemScanner\Events\PhotoCaptured;

beforeEach(function (): void {
    Native::fakeBridge()
        ->respondTo('SecureStorage.Get', ['value' => 'saved-token'])
        ->respondTo('ItemScanner.Availability', ['available' => false, 'reason' => 'Not supported on this phone.']);
    Http::preventStrayRequests();
    seedSunnyData();

    $this->dataFolder = storage_path('framework/testing/data-folder-'.Str::random(8));
    $this->upload = UploadedFile::fake()->image('photo.jpg');
    $this->photo = $this->upload->getPathname();
    app()->useStoragePath($this->dataFolder.'/installed/storage');

    $this->moveDataFolder = function (): void {
        File::moveDirectory($this->dataFolder.'/installed', $this->dataFolder.'/updated');
        app()->useStoragePath($this->dataFolder.'/updated/storage');
    };
    $this->showsImage = fn (string $path): Closure => fn (array $node): bool => ($node['props']['src'] ?? null) === $path;
    $this->uploadedPhotos = [];
    $this->fakeSunny = fn (Closure $saved) => Saloon::fake([SaveRecordRequest::class => function (PendingRequest $request) use ($saved): MockResponse {
        $this->uploadedPhotos[] = $request->body() instanceof MultipartBodyRepository ? (string) $request->body()->get('photo')?->value : null;

        return MockResponse::make(['data' => $saved($request->getRequest()->resolveEndpoint())], 201);
    }]);
});

afterEach(fn () => File::deleteDirectory($this->dataFolder));

it('still shows downloaded thumbnails after the data folder moves', function (): void {
    $thumbUrl = fn (string $name): string => "https://cdn.sunny.example/thumbs/{$name}.jpg";
    File::ensureDirectoryExists(SunnyThumbnails::directory());
    foreach (['pancakes', 'kitchen', 'drill'] as $name) {
        File::put(SunnyThumbnails::pathFor($thumbUrl($name)), $name);
    }
    Recipe::find(1)->update(['thumb_url' => $thumbUrl('pancakes'), 'thumb_path' => SunnyThumbnails::pathFor($thumbUrl('pancakes'))]);
    Item::find(1)->update(['thumb_url' => $thumbUrl('kitchen'), 'thumb_path' => SunnyThumbnails::pathFor($thumbUrl('kitchen'))]);
    ItemPhoto::create([
        'id' => 501, 'server' => Item::find(8)->server, 'item_id' => 8, 'position' => 0, 'url' => 'https://sunny.example/photos/501.jpg',
        'thumb_url' => $thumbUrl('drill'), 'thumb_path' => SunnyThumbnails::pathFor($thumbUrl('drill')),
    ]);

    ($this->moveDataFolder)();

    $pancakes = SunnyThumbnails::pathFor($thumbUrl('pancakes'));
    $kitchen = SunnyThumbnails::pathFor($thumbUrl('kitchen'));
    $drill = SunnyThumbnails::pathFor($thumbUrl('drill'));
    expect($pancakes)->toStartWith($this->dataFolder.'/updated/storage/');
    Native::visit('/recipes')->assertElement('image', ($this->showsImage)($pancakes));
    Native::visit('/inventory')->assertElement('image', ($this->showsImage)($kitchen));
    expect(array_column(Native::visit('/dashboard')->get('recentRecipes'), 'photo'))->toContain($pancakes)
        ->and(Native::visit('/inventory/8')->get('photos')[0]['thumb'])->toBe($drill);

    app(SunnyThumbnails::class)->prune();

    expect([$pancakes, $kitchen, $drill])->each->toBeFile();
});

it('still shows and uploads photos waiting for Sunny after the data folder moves', function (): void {
    $outbox = app(SunnyOutbox::class);
    $outbox->queue('recipes', 1, ['name' => 'Buttermilk Pancakes'], 1, $this->photo);
    $photoId = $outbox->queue('item_photos', 1, ['item_id' => 8], null, $this->photo);
    Item::find(8)->update(['photo_url' => ItemPhoto::find($photoId)->url]);

    ($this->moveDataFolder)();

    $recipePhoto = Recipe::find(1)->photo_url;
    $itemPhoto = ItemPhoto::find($photoId)->url;
    expect([$recipePhoto, $itemPhoto])->each->toStartWith(SunnyOutbox::photoDirectory().'/')->toBeFile()
        ->and(PendingWrite::query()->orderBy('id')->pluck('photo_path')->all())->toBe([$recipePhoto, $itemPhoto]);
    Native::visit('/recipes')->assertElement('image', ($this->showsImage)($recipePhoto));
    Native::visit('/recipes/1')->assertElement('image', ($this->showsImage)($recipePhoto));
    Native::visit('/inventory/7')->assertElement('image', ($this->showsImage)($itemPhoto));
    expect(Native::visit('/inventory/8')->get('photos')[0])->toMatchArray(['url' => $itemPhoto, 'thumb' => $itemPhoto]);

    $outbox->prunePhotos();

    expect([$recipePhoto, $itemPhoto])->each->toBeFile();

    ($this->fakeSunny)(fn (string $endpoint): array => str_ends_with($endpoint, '/photos')
        ? ['id' => 600, 'item_id' => 8, 'position' => 0, 'url' => 'https://sunny.example/photos/600.jpg', 'thumb_url' => null]
        : [...Recipe::find(1)->toArray(), 'photo_url' => 'https://sunny.example/pancakes.jpg']);

    app(SunnyWrites::class)->push();

    expect($this->uploadedPhotos)->toBe([file_get_contents($this->photo), file_get_contents($this->photo)])
        ->and(PendingWrite::count())->toBe(0);
});

it('still shows and adds Bulk add photos after the data folder moves', function (): void {
    Native::visit('/inventory/scan', ['parent' => 7])
        ->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => $this->photo])
        ->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => $this->photo])
        ->call('renameCandidate', 1, 'Hammer')
        ->call('renameCandidate', 2, 'Level');

    ($this->moveDataFolder)();

    $screen = Native::visit('/inventory/scan', ['parent' => 7]);
    [$level, $hammer] = array_column($screen->get('candidates'), 'photoPath');
    expect([$level, $hammer])->each->toStartWith(ScanDraft::photoDirectory().'/')->toBeFile();
    $screen->assertElement('image', ($this->showsImage)($hammer))
        ->assertElement('image', ($this->showsImage)($level));

    $screen->call('removeCandidate', 2);

    expect($level)->not->toBeFile();

    ($this->fakeSunny)(fn (): array => ['id' => 100, 'team_id' => 1, 'parent_id' => 7, 'type' => 'item', 'name' => 'Hammer', 'metadata' => null, 'photo_url' => null]);
    $screen->tap('scan-submit')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Add', 'id' => 'add-items'])
        ->assertSet('error', '');

    expect($this->uploadedPhotos)->toBe([file_get_contents($this->photo)])
        ->and(File::files(ScanDraft::photoDirectory()))->toBe([]);
});

it('clears Bulk add photos when a draft is discarded after the data folder moves', function (): void {
    Native::visit('/inventory/scan')->emitNative(PhotoCaptured::class, ['id' => 'scan', 'path' => $this->photo]);

    ($this->moveDataFolder)();
    ScanDraft::sole()->discard();

    expect(File::files(ScanDraft::photoDirectory()))->toBe([]);
});
