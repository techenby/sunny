<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Recipe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class UpdateRecipe
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Recipe $recipe, array $data, bool $removePhoto = false): Recipe
    {
        $photo = Arr::pull($data, 'photo');

        $recipe->update($data);

        if ($removePhoto && ! $photo instanceof UploadedFile) {
            Storage::delete(array_filter([$recipe->photo_path, $recipe->thumb_path]));

            $recipe->update(['photo_path' => null, 'thumb_path' => null]);
        } elseif ($photo instanceof UploadedFile) {
            Storage::delete(array_filter([$recipe->photo_path, $recipe->thumb_path]));

            $filename = "{$recipe->slug}.{$photo->getClientOriginalExtension()}";

            $path = $photo->storeAs("teams/{$recipe->team_id}/recipes", $filename);

            $recipe->update(['photo_path' => $path, 'thumb_path' => (new CreateThumbnail)->handle($path)]);
        }

        return $recipe;
    }
}
