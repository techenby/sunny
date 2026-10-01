<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Routines\CreateRoutine;
use App\Actions\Routines\DeleteRoutine;
use App\Actions\Routines\UpdateRoutine;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRoutineRequest;
use App\Http\Requests\Api\UpdateRoutineRequest;
use App\Http\Resources\RoutineResource;
use App\Models\Routine;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RoutineController extends Controller
{
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Routine::class);

        $routines = $team->routines()->with(['user', 'steps'])->orderBy('name')->get();

        return RoutineResource::collection($routines);
    }

    public function store(StoreRoutineRequest $request, Team $team, CreateRoutine $action): JsonResponse
    {
        $existing = $request->filled('client_uuid')
            ? $team->routines()->withTrashed()->firstWhere('client_uuid', $request->validated('client_uuid'))
            : null;

        $routine = $existing ?? $action->handle($team, $request->validated());

        return RoutineResource::make($routine->load(['user', 'steps']))
            ->response()
            ->setStatusCode($existing ? 200 : 201);
    }

    public function show(Team $team, Routine $routine): RoutineResource
    {
        Gate::authorize('view', $routine);

        return RoutineResource::make($routine->load(['user', 'steps']));
    }

    public function update(UpdateRoutineRequest $request, Team $team, Routine $routine, UpdateRoutine $action): RoutineResource
    {
        $routine = $action->handle($routine, $request->validated());

        return RoutineResource::make($routine->load(['user', 'steps']));
    }

    public function destroy(Team $team, Routine $routine, DeleteRoutine $action): Response
    {
        Gate::authorize('delete', $routine);

        $action->handle($routine);

        return response()->noContent();
    }
}
