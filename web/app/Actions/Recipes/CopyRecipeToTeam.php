<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\Team;

class CopyRecipeToTeam
{
    public function handle(Recipe $recipe, Team $team, ?string $clientUuid = null): Recipe
    {
        $copy = $recipe->replicate(['client_uuid'])
            ->fill([
                'team_id' => $team->id,
                'parent_id' => $recipe->id,
                'share_token' => null,
                'client_uuid' => $clientUuid,
            ]);
        $copy->save();

        return $copy;
    }
}
