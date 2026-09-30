<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\UpdateRoutineOccurrenceStepRequest;
use App\Http\Resources\RoutineOccurrenceStepResource;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;

class RoutineOccurrenceStepController extends Controller
{
    public function update(UpdateRoutineOccurrenceStepRequest $request, Team $team, RoutineOccurrence $routineOccurrence, RoutineOccurrenceStep $step): RoutineOccurrenceStepResource
    {
        $completed = $request->boolean('completed');

        if ($completed !== $step->isCompleted()) {
            $completed ? $step->complete($request->user()) : $step->uncomplete();
        }

        return RoutineOccurrenceStepResource::make($step->load('step'));
    }
}
