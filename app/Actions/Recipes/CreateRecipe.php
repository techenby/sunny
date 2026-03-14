<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class CreateRecipe
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data): Recipe
    {
        $photo = Arr::pull($data, 'photo');

        $recipe = $team->recipes()->create($data);

        if ($photo instanceof UploadedFile) {
            $filename = "{$recipe->slug}.{$photo->getClientOriginalExtension()}";

            $path = $photo->storeAs("teams/{$team->id}/recipes", $filename);

            $recipe->update(['photo_path' => $path]);
        }

        return $recipe;
    }
}
