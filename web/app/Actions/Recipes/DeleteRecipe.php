<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\Recipe;

class DeleteRecipe
{
    public function handle(Recipe $recipe): void
    {
        $recipe->delete();
    }
}
