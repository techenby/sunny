<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ItemResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\TeamResource;
use App\Models\Item;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $user = Auth::user();
        $teamIds = $user->teams()->pluck('teams.id');

        $teamsQuery = $user->teams()->withTrashed();
        $recipesQuery = Recipe::query()->withTrashed()->whereIn('team_id', $teamIds);
        $itemsQuery = Item::query()->withTrashed()->whereIn('team_id', $teamIds);

        if ($since = $request->date('since')) {
            $teamsQuery->where('teams.updated_at', '>=', $since);
            $recipesQuery->where('updated_at', '>=', $since);
            $itemsQuery->where('updated_at', '>=', $since);
        }

        return response()->json([
            'teams' => TeamResource::collection($teamsQuery->get()),
            'recipes' => RecipeResource::collection($recipesQuery->get()),
            'items' => ItemResource::collection($itemsQuery->get()),
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
