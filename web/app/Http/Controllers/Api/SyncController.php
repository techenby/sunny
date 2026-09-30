<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Http\Controllers\Controller;
use App\Http\Resources\ChecklistItemResource;
use App\Http\Resources\ChecklistResource;
use App\Http\Resources\ItemResource;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\RoutineOccurrenceResource;
use App\Http\Resources\TeamResource;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Contracts\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SyncController extends Controller
{
    public function __invoke(Request $request, GenerateRoutineOccurrences $routines): JsonResponse
    {
        $request->validate([
            'since' => ['nullable', 'date'],
        ]);

        $user = Auth::user();
        $teamIds = $user->teams()->pluck('teams.id');

        $teamsQuery = $user->teams()->withTrashed();
        $recipesQuery = Recipe::query()->withTrashed()->whereIn('team_id', $teamIds);
        $itemsQuery = Item::query()->withTrashed()->whereIn('team_id', $teamIds);
        $checklistsQuery = Checklist::query()->withTrashed()->whereIn('team_id', $teamIds);
        $checklistItemsQuery = ChecklistItem::query()->whereHas('checklist', fn (Builder $query) => $query->whereIn('team_id', $teamIds));

        if ($since = $request->date('since')) {
            $teamsQuery->where('teams.updated_at', '>=', $since);
            $recipesQuery->where('updated_at', '>=', $since);
            $itemsQuery->where('updated_at', '>=', $since);
            $checklistsQuery->where('updated_at', '>=', $since);
            $checklistItemsQuery->where('updated_at', '>=', $since);
        }

        return response()->json([
            'teams' => TeamResource::collection($teamsQuery->get()),
            'recipes' => RecipeResource::collection($recipesQuery->get()),
            'items' => ItemResource::collection($itemsQuery->get()),
            'checklists' => ChecklistResource::collection($checklistsQuery->get()),
            'checklist_items' => ChecklistItemResource::collection($checklistItemsQuery->get()),
            'routine_occurrences' => RoutineOccurrenceResource::collection($user->teams()->get()->flatMap(
                fn (Team $team) => [
                    ...$routines->forDate($team, $team->today()),
                    ...$routines->forDate($team, $team->today()->addDay()),
                ],
            )),
            'synced_at' => now()->toIso8601String(),
        ]);
    }
}
