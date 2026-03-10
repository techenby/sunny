<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

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
            $path = "teams/{$team->id}/recipes/{$recipe->slug}.{$photo->getClientOriginalExtension()}";

            Storage::put($path, $photo);

            $recipe->update(['photo_path' => $path]);
        }

        return $recipe;
    }
}
