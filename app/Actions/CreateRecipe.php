<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Recipe;
use App\Models\Team;

class CreateRecipe
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): Recipe
    {
        return $team->recipes()->create($data);
    }
}
