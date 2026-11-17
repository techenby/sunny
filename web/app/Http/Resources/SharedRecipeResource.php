<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Recipe;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/** @mixin Recipe */
class SharedRecipeResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...$this->only([
                'id', 'team_id', 'name', 'source', 'servings', 'prep_time', 'cook_time', 'total_time',
                'description', 'ingredients', 'instructions', 'notes', 'nutrition', 'share_token',
            ]),
            'share_url' => route('recipes.shared', $this->share_token),
            'photo_url' => $this->photo_path ? Storage::temporaryUrl($this->photo_path, now()->addMinutes(30)) : null,
            'thumb_url' => $this->thumb_path ? Storage::temporaryUrl($this->thumb_path, now()->addMinutes(30)) : null,
        ];
    }
}
