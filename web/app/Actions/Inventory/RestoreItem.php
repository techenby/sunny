<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;

class RestoreItem
{
    public function handle(Item $item): Item
    {
        if (! $item->trashed()) {
            return $item;
        }

        if ($item->parent_id !== null && $item->parent()->doesntExist()) {
            $item->parent_id = null;
        }

        $item->restore();

        return $item;
    }
}
