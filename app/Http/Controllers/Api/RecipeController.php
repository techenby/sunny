<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Recipes\CreateRecipe;
use App\Actions\Recipes\DeleteRecipe;
use App\Actions\Recipes\UpdateRecipe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecipeRequest;
use App\Http\Requests\Api\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\Team;
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
        $recipe = $action->handle($team, $request->validated());

        return RecipeResource::make($recipe)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Team $team, Recipe $recipe): RecipeResource
    {
        Gate::authorize('view', $recipe);

        return RecipeResource::make($recipe);
    }

    public function update(UpdateRecipeRequest $request, Team $team, Recipe $recipe, UpdateRecipe $action): RecipeResource
    {
        $recipe = $action->handle($recipe, $request->validated());

        return RecipeResource::make($recipe);
    }

    public function destroy(Team $team, Recipe $recipe, DeleteRecipe $action): Response
    {
        Gate::authorize('delete', $recipe);

        $action->handle($recipe);

        return response()->noContent();
    }
}
