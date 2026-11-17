<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RecipeResource;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Support\Facades\Gate;

class RecipeShareController extends Controller
{
    public function update(Team $team, Recipe $recipe): RecipeResource
    {
        Gate::authorize('share', $recipe);

        if (! $recipe->isShared()) {
            $recipe->enableSharing();
        }

        return RecipeResource::make($recipe);
    }

    public function destroy(Team $team, Recipe $recipe): RecipeResource
    {
        Gate::authorize('share', $recipe);

        $recipe->disableSharing();

        return RecipeResource::make($recipe);
    }
}
