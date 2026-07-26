<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class DuplicateItem
{
    /** @return Collection<int, Item> */
    public function handle(Item $item, int $count = 1): Collection
    {
        return Collection::times($count, function () use ($item): Item {
            $copy = $item->replicate(['photo_path']);
            $copy->save();

            if ($item->photo_path) {
                $extension = pathinfo($item->photo_path, PATHINFO_EXTENSION);
                $path = "teams/{$item->team_id}/items/" . Str::slug($copy->name) . '-' . $copy->id . '.' . $extension;

                Storage::copy($item->photo_path, $path);
                $copy->update(['photo_path' => $path]);
            }

            return $copy;
        });
    }
}
