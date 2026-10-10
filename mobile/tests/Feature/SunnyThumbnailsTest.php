<?php

use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnyThumbnails;
use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    File::deleteDirectory(SunnyThumbnails::directory());
    Http::preventStrayRequests();
    $this->sync = function (?string $itemThumb, ?string $recipeThumb = null): void {
        Saloon::fake([SyncRequest::class => MockResponse::make([
            'teams' => [['id' => 7, 'name' => 'Family']],
            'recipes' => [['id' => 42, 'team_id' => 7, 'name' => 'Soup', 'photo_url' => $recipeThumb ? 'https://cdn.sunny.example/soup.jpg' : null, 'thumb_url' => $recipeThumb, 'created_at' => '2026-09-01T12:00:00+00:00', 'updated_at' => '2026-09-23T12:00:00+00:00']],
            'items' => [['id' => 10, 'team_id' => 7, 'name' => 'Kitchen', 'type' => 'location', 'photo_url' => $itemThumb ? 'https://cdn.sunny.example/kitchen.jpg?signature=full' : null, 'thumb_url' => $itemThumb, 'created_at' => '2026-09-01T12:00:00+00:00', 'updated_at' => '2026-09-23T12:00:00+00:00']],
            'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
            'synced_at' => '2026-09-23T13:00:00+00:00',
        ])]);
        app(SunnySync::class)->sync();
    };
});

afterEach(fn () => File::deleteDirectory(SunnyThumbnails::directory()));

it('keeps a downloaded thumbnail on the phone and shows it in lists', function (): void {
    Http::fake(['cdn.sunny.example/*' => Http::response('thumb-bytes')]);

    ($this->sync)('https://cdn.sunny.example/thumbs/kitchen-a1.jpg?signature=one', 'https://cdn.sunny.example/thumbs/soup-b2.jpg?signature=one');

    $itemThumb = Item::find(10)->thumb_path;
    expect($itemThumb)->toBe(SunnyThumbnails::pathFor('https://cdn.sunny.example/thumbs/kitchen-a1.jpg'))
        ->and(file_get_contents($itemThumb))->toBe('thumb-bytes')
        ->and(Recipe::find(42)->thumb_path)->toBeFile();
    Http::assertSentCount(2);

    Native::visit('/inventory')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Kitchen'
            && ($node['props']['leading_value'] ?? null) === $itemThumb)
        ->input('updateSearch', 'kitchen')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Kitchen'
            && ($node['props']['leading_value'] ?? null) === $itemThumb);
    Native::visit('/recipes')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Soup'
            && ($node['props']['leading_value'] ?? null) === Recipe::find(42)->thumb_path);
    expect(Native::visit('/dashboard')->get('recentItems')[0]['photo'])->toBe($itemThumb);
});

it('does not download a thumbnail again when Sunny signs a new URL for the same photo', function (): void {
    Http::fake(['cdn.sunny.example/*' => Http::response('thumb-bytes')]);
    ($this->sync)('https://cdn.sunny.example/thumbs/kitchen-a1.jpg?signature=one');

    ($this->sync)('https://cdn.sunny.example/thumbs/kitchen-a1.jpg?signature=two');

    Http::assertSentCount(1);
    expect(Item::find(10)->thumb_path)->toBeFile();
});

it('swaps in the new thumbnail when the photo changes and forgets the old one', function (): void {
    Http::fake(['cdn.sunny.example/*' => Http::response('thumb-bytes')]);
    ($this->sync)('https://cdn.sunny.example/thumbs/kitchen-a1.jpg?signature=one');
    $old = Item::find(10)->thumb_path;

    ($this->sync)('https://cdn.sunny.example/thumbs/kitchen-c3.jpg?signature=one');

    expect(Item::find(10)->thumb_path)->not->toBe($old)->toBeFile()
        ->and($old)->not->toBeFile();

    ($this->sync)(null);

    expect(Item::find(10)->thumb_path)->toBeNull()
        ->and(File::files(SunnyThumbnails::directory()))->toBe([]);
});

it('falls back to the full photo and retries when a thumbnail fails to download', function (): void {
    Http::fakeSequence('cdn.sunny.example/*')->push('', 500)->push('thumb-bytes');

    ($this->sync)('https://cdn.sunny.example/thumbs/kitchen-a1.jpg?signature=one');

    expect(Item::find(10)->thumb_path)->toBeNull();
    Native::visit('/inventory')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Kitchen'
            && ($node['props']['leading_value'] ?? null) === 'https://cdn.sunny.example/kitchen.jpg?signature=full');

    ($this->sync)('https://cdn.sunny.example/thumbs/kitchen-a1.jpg?signature=two');

    expect(Item::find(10)->thumb_path)->toBeFile();
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'signature=two'));
});

it('shows a newly chosen photo until Sunny has made its thumbnail', function (): void {
    Http::fake(['cdn.sunny.example/*' => Http::response('thumb-bytes')]);
    ($this->sync)('https://cdn.sunny.example/thumbs/kitchen-a1.jpg?signature=one');

    $photo = UploadedFile::fake()->image('kitchen.jpg');

    app(SunnyOutbox::class)->queue('items', 7, ['name' => 'Kitchen'], 10, $photo->getPathname());

    $item = Item::find(10);
    expect($item->thumb_url)->toBeNull()
        ->and($item->thumb_path)->toBeNull()
        ->and($item->photo_url)->toStartWith(SunnyOutbox::photoDirectory());
    Native::visit('/inventory')
        ->assertElement('list_item', fn (array $node): bool => ($node['props']['headline'] ?? null) === 'Kitchen'
            && ($node['props']['leading_value'] ?? null) === $item->photo_url);
});
