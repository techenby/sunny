<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\CreateRecipe;
use App\Actions\DeleteRecipe;
use App\Actions\UpdateRecipe;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRecipeRequest;
use App\Http\Requests\Api\UpdateRecipeRequest;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class RecipeController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Recipe::class);

        $recipes = Auth::user()->currentTeam->recipes()->latest()->get();

        return RecipeResource::collection($recipes);
    }

    public function store(StoreRecipeRequest $request, CreateRecipe $action): JsonResponse
    {
        $recipe = $action->handle(Auth::user()->currentTeam, $request->validated());

        return RecipeResource::make($recipe)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Recipe $recipe): RecipeResource
    {
        Gate::authorize('view', $recipe);

        return RecipeResource::make($recipe);
    }

    public function update(UpdateRecipeRequest $request, Recipe $recipe, UpdateRecipe $action): RecipeResource
    {
        $recipe = $action->handle($recipe, $request->validated());

        return RecipeResource::make($recipe);
    }

    public function destroy(Recipe $recipe, DeleteRecipe $action): Response
    {
        Gate::authorize('delete', $recipe);

        $action->handle($recipe);

        return response()->noContent();
    }
}
