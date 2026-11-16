<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\ItemPhoto;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin ItemPhoto */
class ItemPhotoResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'item_id' => $this->item_id,
            'position' => $this->position,
            'url' => $this->url,
            'thumb_url' => $this->thumb_url,
            'client_uuid' => $this->client_uuid,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
