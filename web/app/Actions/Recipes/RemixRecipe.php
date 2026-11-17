<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\Recipe;

class RemixRecipe
{
    public function handle(Recipe $recipe): Recipe
    {
        $remix = $recipe->replicate(['client_uuid', 'photo_path', 'thumb_path'])
            ->fill([
                'name' => $recipe->name . ' (Remix)',
                'parent_id' => $recipe->id,
            ]);
        $remix->save();

        (new CopyRecipePhoto)->handle($recipe, $remix);

        return $remix;
    }
}
