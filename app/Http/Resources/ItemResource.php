<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Storage;

class ItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...Arr::except(parent::toArray($request), ['photo_path']),
            'photo_url' => $this->photo_path ? Storage::temporaryUrl($this->photo_path, now()->addMinutes(30)) : null,
        ];
    }
}
