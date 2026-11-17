<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Models\Item;
use App\Models\Team;
use Illuminate\Support\Facades\DB;

class MoveItemToTeam
{
    public function handle(Item $item, Team $team): Item
    {
        DB::transaction(function () use ($item, $team): void {
            Item::withTrashed()
                ->whereIn('id', $item->descendantIds())
                ->update(['team_id' => $team->id]);

            $item->update([
                'team_id' => $team->id,
                'parent_id' => null,
            ]);
        });

        return $item;
    }
}
