<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ForceDeleteItem
{
    public function handle(Item $item): void
    {
        $paths = $item->photos()->get(['path', 'thumb_path'])
            ->flatMap(fn (ItemPhoto $photo) => [$photo->path, $photo->thumb_path])
            ->filter()
            ->values()
            ->all();

        DB::transaction(function () use ($item): void {
            $item->children()->withTrashed()->update(['parent_id' => null]);
            $item->forceDelete();
        });

        Storage::delete($paths);
    }
}
