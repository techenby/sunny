<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Recipe;

class UpdateRecipe
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Recipe $recipe, array $data): Recipe
    {
        $recipe->update($data);

        return $recipe;
    }
}
