<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;

class CreateRecipe
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Team $team, array $data, bool $withoutParentPhoto = false): Recipe
    {
        $photo = Arr::pull($data, 'photo');

        $recipe = $team->recipes()->create($data);

        if ($photo instanceof UploadedFile) {
            $filename = "{$recipe->slug}.{$photo->getClientOriginalExtension()}";

            $path = $photo->storeAs("teams/{$team->id}/recipes", $filename);

            $recipe->update(['photo_path' => $path, 'thumb_path' => (new CreateThumbnail)->handle($path)]);
        } elseif ($recipe->parent_id !== null && ! $withoutParentPhoto) {
            $parent = $team->recipes()->withTrashed()->find($recipe->parent_id);

            if ($parent !== null) {
                (new CopyRecipePhoto)->handle($parent, $recipe);
            }
        }

        return $recipe;
    }
}
