<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\CalendarFeed;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin CalendarFeed */
class CalendarFeedResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'team_id' => $this->team_id,
            'name' => $this->name,
            'url' => $this->url,
            'color' => $this->color,
            'status' => $this->isFailing() ? 'failing' : 'ok',
            'last_fetched_at' => $this->last_fetched_at,
            'last_failed_at' => $this->last_failed_at,
            'last_error' => $this->last_error,
            'client_uuid' => $this->client_uuid,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
