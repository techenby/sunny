<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\Team;

class SaveSharedRecipeToTeam
{
    public function handle(Recipe $recipe, Team $team, ?string $clientUuid = null): Recipe
    {
        if ($recipe->team_id === $team->id) {
            return $recipe;
        }

        $source = route('recipes.shared', $recipe->share_token);

        return $team->recipes()->firstWhere('source', $source)
            ?? (new CreateRecipe)->handle($team, [
                ...$recipe->only([
                    'name', 'servings', 'prep_time', 'cook_time', 'total_time',
                    'description', 'ingredients', 'instructions', 'notes', 'nutrition',
                ]),
                'source' => $source,
                'client_uuid' => $clientUuid,
            ]);
    }
}
