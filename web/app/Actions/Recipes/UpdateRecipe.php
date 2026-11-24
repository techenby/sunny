<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Recipe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

class UpdateRecipe
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Recipe $recipe, array $data, bool $removePhoto = false): Recipe
    {
        $photo = Arr::pull($data, 'photo');

        $recipe->update($data);

        $oldPaths = [$recipe->photo_path, $recipe->thumb_path];

        if ($removePhoto && ! $photo instanceof UploadedFile) {
            $recipe->update(['photo_path' => null, 'thumb_path' => null]);

            (new DeleteUnusedRecipePhotos)->handle($oldPaths);
        } elseif ($photo instanceof UploadedFile) {
            $path = $photo->storeAs("teams/{$recipe->team_id}/recipes", $this->filename($recipe, $photo));

            $recipe->update(['photo_path' => $path, 'thumb_path' => (new CreateThumbnail)->handle($path)]);

            (new DeleteUnusedRecipePhotos)->handle($oldPaths);
        }

        return $recipe;
    }

    private function filename(Recipe $recipe, UploadedFile $photo): string
    {
        $extension = $photo->extension();
        $filename = "{$recipe->slug}.{$extension}";

        $takenByAnotherRecipe = Recipe::withTrashed()
            ->whereKeyNot($recipe->id)
            ->where('photo_path', "teams/{$recipe->team_id}/recipes/{$filename}")
            ->exists();

        return $takenByAnotherRecipe ? "{$recipe->slug}-" . Str::lower(Str::random(8)) . ".{$extension}" : $filename;
    }
}
