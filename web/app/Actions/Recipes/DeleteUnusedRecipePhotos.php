<?php

declare(strict_types=1);

namespace App\Actions\Recipes;

use App\Models\Recipe;
use Illuminate\Support\Facades\Storage;

class DeleteUnusedRecipePhotos
{
    /** @param  array<int, string|null>  $paths */
    public function handle(array $paths): void
    {
        $paths = array_values(array_filter($paths));

        if ($paths === []) {
            return;
        }

        $used = Recipe::withTrashed()
            ->where(fn ($query) => $query->whereIn('photo_path', $paths)->orWhereIn('thumb_path', $paths))
            ->get(['photo_path', 'thumb_path'])
            ->flatMap(fn (Recipe $recipe) => [$recipe->photo_path, $recipe->thumb_path])
            ->filter()
            ->all();

        Storage::delete(array_values(array_diff($paths, $used)));
    }
}
