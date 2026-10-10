<?php

namespace App\Http\Integrations\Sunny;

use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;

class SunnyThumbnails
{
    private const CONCURRENCY = 6;

    /**
     * Download the thumbnails this phone doesn't have yet, so lists show photos without going back to Sunny.
     */
    public function refresh(): void
    {
        foreach ([Item::class, Recipe::class] as $model) {
            $model::query()->forCurrentServer()->whereNull('thumb_url')->whereNotNull('thumb_path')->update(['thumb_path' => null]);

            $missing = $model::query()->forCurrentServer()->whereNotNull('thumb_url')->get(['id', 'thumb_url', 'thumb_path'])
                ->reject(fn (Item|Recipe $record): bool => $record->thumb_path === self::pathFor($record->thumb_url) && is_file($record->thumb_path));

            foreach ($missing->chunk(self::CONCURRENCY) as $chunk) {
                $downloads = $chunk->reject(fn (Item|Recipe $record): bool => is_file(self::pathFor($record->thumb_url)));
                $responses = $downloads->isEmpty() ? [] : Http::pool(fn (Pool $pool) => $downloads
                    ->map(fn (Item|Recipe $record) => $pool->as((string) $record->id)->timeout(15)->get($record->thumb_url))
                    ->all());

                foreach ($chunk as $record) {
                    $path = self::pathFor($record->thumb_url);
                    $response = $responses[(string) $record->id] ?? null;

                    if ($response instanceof Response && $response->successful()) {
                        File::ensureDirectoryExists(self::directory());
                        File::put($path, $response->body());
                    }

                    if (is_file($path)) {
                        $model::query()->whereKey($record->id)->update(['thumb_path' => $path]);
                    }
                }
            }
        }

        $this->prune();
    }

    /**
     * Delete downloaded thumbnails that no local record points at any more.
     */
    public function prune(): void
    {
        $inUse = Item::query()->pluck('thumb_path')
            ->merge(Recipe::query()->pluck('thumb_path'))
            ->filter()->flip();

        foreach (File::glob(self::directory().'/*') as $path) {
            if (! $inUse->has($path)) {
                File::delete($path);
            }
        }
    }

    public static function directory(): string
    {
        return storage_path('app/sunny-thumbs');
    }

    /**
     * Sunny signs a new URL on every sync, but the file it points at only changes with the photo.
     */
    public static function pathFor(string $url): string
    {
        return self::directory().'/'.md5(parse_url($url, PHP_URL_PATH) ?: $url).'.jpg';
    }
}
