<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\RoutineOccurrenceStep;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutineOccurrenceResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routine_id' => $this->routine_id,
            'due_on' => $this->due_on->toDateString(),
            'routine' => RoutineResource::make($this->whenLoaded('routine')),
            'steps' => $this->whenLoaded('steps', fn () => RoutineOccurrenceStepResource::collection(
                $this->steps->sortBy(fn (RoutineOccurrenceStep $step): array => [$step->step->position, $step->id])->values(),
            )),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
