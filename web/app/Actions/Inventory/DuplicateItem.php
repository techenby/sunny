<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DuplicateItem
{
    /** @return Collection<int, Item> */
    public function handle(Item $item, int $count = 1): Collection
    {
        $photos = $item->photos()->get();

        return Collection::times($count, function () use ($item, $photos): Item {
            $copy = $item->replicate(['client_uuid']);
            $copy->save();

            $photos->each(function (ItemPhoto $photo) use ($copy): void {
                $extension = pathinfo($photo->path, PATHINFO_EXTENSION);
                $path = "teams/{$copy->team_id}/items/" . Str::slug($copy->name) . '-' . $copy->id . '-' . Str::lower(Str::random(8)) . '.' . $extension;

                Storage::copy($photo->path, $path);

                $copy->photos()->create([
                    'path' => $path,
                    'thumb_path' => $photo->thumb_path ? (new CreateThumbnail)->copy($photo->thumb_path, $path) : null,
                    'position' => $photo->position,
                ]);
            });

            return $copy;
        });
    }
}
