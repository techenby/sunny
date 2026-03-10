<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Recipe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class UpdateRecipe
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function handle(Recipe $recipe, array $data): Recipe
    {
        $photo = Arr::pull($data, 'photo');

        $recipe->update($data);

        if ($photo instanceof UploadedFile) {
            if ($recipe->photo_path) {
                Storage::delete($recipe->photo_path);
            }

            $path = "teams/{$recipe->team_id}/recipes/{$recipe->slug}.{$photo->getClientOriginalExtension()}";

            Storage::put($path, $photo);

            $recipe->update(['photo_path' => $path]);
        }

        return $recipe;
    }
}
