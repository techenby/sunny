<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Http\Controllers\Controller;
use App\Http\Resources\RoutineOccurrenceResource;
use App\Models\Routine;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RoutineOccurrenceController extends Controller
{
    public function index(Request $request, Team $team, GenerateRoutineOccurrences $action): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Routine::class);

        $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $date = $request->filled('date')
            ? CarbonImmutable::parse($request->input('date'), $team->timezone)
            : $team->today();

        return RoutineOccurrenceResource::collection($action->forDate($team, $date));
    }
}
