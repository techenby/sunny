<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use App\Models\Team;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class RoutineController extends Controller
{
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Routine::class);

        $routines = $team->routines()->with(['user', 'steps'])->orderBy('name')->get();

        return RoutineResource::collection($routines);
    }

    public function show(Team $team, Routine $routine): RoutineResource
    {
        Gate::authorize('view', $routine);

        return RoutineResource::make($routine->load(['user', 'steps']));
    }
}
