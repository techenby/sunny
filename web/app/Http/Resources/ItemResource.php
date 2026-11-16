<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Item;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/** @mixin Item */
class ItemResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $photos = $this->photos;
        $primary = $photos->first();

        return [
            ...Arr::except(parent::toArray($request), ['primary_photo']),
            'photo_url' => $primary?->url,
            'thumb_url' => $primary?->thumb_url,
            'photos' => ItemPhotoResource::collection($photos),
        ];
    }
}
