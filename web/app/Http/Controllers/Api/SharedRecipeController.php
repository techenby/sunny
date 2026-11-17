<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Recipes\SaveSharedRecipeToTeam;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\SaveSharedRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Http\Resources\SharedRecipeResource;
use App\Models\Recipe;
use App\Support\ClientUuid;
use Illuminate\Http\JsonResponse;

class SharedRecipeController extends Controller
{
    public function show(Recipe $recipe): SharedRecipeResource
    {
        return SharedRecipeResource::make($recipe);
    }

    public function save(SaveSharedRecipeRequest $request, Recipe $recipe, SaveSharedRecipeToTeam $action): JsonResponse
    {
        $team = $request->user()->teams()->findOrFail($request->integer('team_id'));

        $saved = ClientUuid::firstOrCreate(
            $team->recipes()->withTrashed(),
            $request->validated('client_uuid'),
            fn (): Recipe => $action->handle($recipe, $team, $request->validated('client_uuid')),
        );

        return RecipeResource::make($saved)
            ->response()
            ->setStatusCode($saved->wasRecentlyCreated ? 201 : 200);
    }
}
