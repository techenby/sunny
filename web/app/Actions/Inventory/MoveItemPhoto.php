<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\ItemPhoto;
use Illuminate\Support\Facades\DB;

class MoveItemPhoto
{
    public function handle(ItemPhoto $photo, int $position): ItemPhoto
    {
        DB::transaction(function () use ($photo, $position): void {
            $others = $photo->item->photos()->whereKeyNot($photo->id)->lockForUpdate()->get();

            $others->splice(max(0, min($position, $others->count())), 0, [$photo]);

            foreach ($others->values() as $index => $each) {
                if ($each->position !== $index) {
                    $each->update(['position' => $index]);
                }
            }
        });

        return $photo;
    }
}
