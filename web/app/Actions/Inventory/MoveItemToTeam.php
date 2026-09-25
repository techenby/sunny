<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use App\Models\Team;

class MoveItemToTeam
{
    public function handle(Item $item, Team $team): Item
    {
        $item->update([
            'team_id' => $team->id,
            'parent_id' => null,
        ]);

        return $item;
    }
}
