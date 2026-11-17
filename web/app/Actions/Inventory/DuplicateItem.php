<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class DuplicateItem
{
    /** @return Collection<int, Item> */
    public function handle(Item $item, int $count = 1): Collection
    {
        $photos = $item->photos()->get();
        $copiedPaths = [];

        try {
            return DB::transaction(function () use ($item, $count, $photos, &$copiedPaths): Collection {
                return Collection::times($count, function () use ($item, $photos, &$copiedPaths): Item {
                    $copy = $item->replicate(['client_uuid']);
                    $copy->save();

                    $photos->each(function (ItemPhoto $photo) use ($copy, &$copiedPaths): void {
                        $extension = pathinfo($photo->path, PATHINFO_EXTENSION);
                        $path = "teams/{$copy->team_id}/items/" . Str::slug($copy->name) . '-' . $copy->id . '-' . Str::lower(Str::random(8)) . '.' . $extension;

                        Storage::copy($photo->path, $path);
                        $copiedPaths[] = $path;

                        $thumbPath = $photo->thumb_path ? (new CreateThumbnail)->copy($photo->thumb_path, $path) : null;
                        $copiedPaths[] = $thumbPath;

                        $copy->photos()->create([
                            'path' => $path,
                            'thumb_path' => $thumbPath,
                            'position' => $photo->position,
                        ]);
                    });

                    return $copy;
                });
            });
        } catch (Throwable $exception) {
            Storage::delete(array_values(array_filter($copiedPaths)));

            throw $exception;
        }
    }
}
