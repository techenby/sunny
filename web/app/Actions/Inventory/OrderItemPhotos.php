<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Support\Facades\DB;

class OrderItemPhotos
{
    /** @param array<int, int> $ids */
    public function handle(Item $item, array $ids): void
    {
        DB::transaction(function () use ($item, $ids): void {
            $photos = $item->photos()->lockForUpdate()->get();
            $order = array_flip(array_values(array_unique($ids)));

            $sorted = $photos->sortBy(fn (ItemPhoto $photo, int $index) => [
                $order[$photo->id] ?? count($order),
                $index,
            ])->values();

            foreach ($sorted as $position => $photo) {
                if ($photo->position !== $position) {
                    $photo->update(['position' => $position]);
                }
            }
        });
    }
}
