<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Item;
use Illuminate\Http\RedirectResponse;

class OpenItemLinkController extends Controller
{
    public function __invoke(Item $item): RedirectResponse
    {
        if (! $item->trashed() && $item->children()->exists()) {
            return to_route('inventory.index', ['current_team' => $item->team, 'parentId' => $item->id]);
        }

        return to_route('inventory.show', ['current_team' => $item->team, 'item' => $item]);
    }
}
