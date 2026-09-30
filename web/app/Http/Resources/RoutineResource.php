<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

class RoutineResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            ...Arr::except(parent::toArray($request), ['user']),
            'schedule_summary' => $this->scheduleSummary(),
            'user' => $this->whenLoaded('user', fn (): ?array => $this->user?->only(['id', 'name'])),
        ];
    }
}
