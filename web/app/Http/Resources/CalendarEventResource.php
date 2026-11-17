<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Enums\CalendarColor;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @property array{feed_id: int, feed_name: string, feed_color: CalendarColor|string, title: string, location: string|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, all_day: bool, response_status: string|null} $resource */
class CalendarEventResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        $event = $this->resource;

        return [
            'feed_id' => $event['feed_id'],
            'feed_name' => $event['feed_name'],
            'feed_color' => $event['feed_color'] instanceof CalendarColor ? $event['feed_color']->value : $event['feed_color'],
            'title' => $event['title'],
            'location' => $event['location'],
            'date' => $event['starts_at']->toDateString(),
            'all_day' => $event['all_day'],
            'starts_at' => $event['starts_at']->toIso8601String(),
            'ends_at' => $event['ends_at']?->toIso8601String(),
            'response_status' => $event['response_status'],
        ];
    }
}
