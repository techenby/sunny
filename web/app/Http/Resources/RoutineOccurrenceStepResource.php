<?php

declare(strict_types=1);

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class RoutineOccurrenceStepResource extends JsonResource
{
    /** @return array<string, mixed> */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'routine_occurrence_id' => $this->routine_occurrence_id,
            'routine_step_id' => $this->routine_step_id,
            'name' => $this->step->name,
            'position' => $this->step->position,
            'completed_at' => $this->completed_at,
            'completed_by' => $this->completed_by,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
