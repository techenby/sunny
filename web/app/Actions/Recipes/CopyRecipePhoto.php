<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Recipe;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class CopyRecipePhoto
{
    public function handle(Recipe $recipe, Recipe $copy): void
    {
        if ($recipe->photo_path === null) {
            return;
        }

        $extension = pathinfo($recipe->photo_path, PATHINFO_EXTENSION);
        $path = "teams/{$copy->team_id}/recipes/{$copy->slug}-" . Str::lower(Str::random(8)) . '.' . $extension;

        if (! Storage::copy($recipe->photo_path, $path)) {
            return;
        }

        $copy->update([
            'photo_path' => $path,
            'thumb_path' => $recipe->thumb_path ? (new CreateThumbnail)->copy($recipe->thumb_path, $path) : null,
        ]);
    }
}
