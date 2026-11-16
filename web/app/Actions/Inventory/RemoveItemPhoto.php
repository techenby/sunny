<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\ItemPhoto;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class RemoveItemPhoto
{
    public function handle(ItemPhoto $photo): void
    {
        DB::transaction(function () use ($photo): void {
            $photo->delete();

            foreach ($photo->item->photos()->get()->values() as $index => $each) {
                if ($each->position !== $index) {
                    $each->update(['position' => $index]);
                }
            }
        });

        Storage::delete(array_filter([$photo->path, $photo->thumb_path]));
    }
}
