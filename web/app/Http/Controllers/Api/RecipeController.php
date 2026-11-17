<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Recipes\CopyRecipeToTeam;
use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\DeleteRecipe;
use App\Actions\Recipes\UpdateRecipe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\CopyRecipeRequest;
use App\Http\Requests\Api\StoreRecipeRequest;
use App\Http\Requests\Api\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\Team;
use App\Support\ClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RecipeController extends Controller
{
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Recipe::class);

        $recipes = $team->recipes()->latest()->get();

        return RecipeResource::collection($recipes);
    }

    public function store(StoreRecipeRequest $request, Team $team, CreateRecipe $action): JsonResponse
    {
        $recipe = ClientUuid::firstOrCreate(
            $team->recipes()->withTrashed(),
            $request->validated('client_uuid'),
            fn (): Recipe => $action->handle($team, $request->validated()),
        );

        return RecipeResource::make($recipe)
            ->response()
            ->setStatusCode($recipe->wasRecentlyCreated ? 201 : 200);
    }

    public function copy(CopyRecipeRequest $request, Team $team, Recipe $recipe, CopyRecipeToTeam $action): JsonResponse
    {
        $target = $request->user()->teams()->findOrFail($request->integer('team_id'));

        $copy = ClientUuid::firstOrCreate(
            $target->recipes()->withTrashed(),
            $request->validated('client_uuid'),
            fn (): Recipe => $action->handle($recipe, $target, $request->validated('client_uuid')),
        );

        return RecipeResource::make($copy)
            ->response()
            ->setStatusCode($copy->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Team $team, Recipe $recipe): RecipeResource
    {
        Gate::authorize('view', $recipe);

        return RecipeResource::make($recipe);
    }

    public function update(UpdateRecipeRequest $request, Team $team, Recipe $recipe, UpdateRecipe $action): RecipeResource
    {
        $recipe = $action->handle($recipe, $request->safe()->except('remove_photo'), $request->boolean('remove_photo'));

        return RecipeResource::make($recipe);
    }

    public function destroy(Team $team, Recipe $recipe, DeleteRecipe $action): Response
    {
        Gate::authorize('delete', $recipe);

        $action->handle($recipe);

        return response()->noContent();
    }
}
