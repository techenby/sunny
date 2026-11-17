<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreRoutineStepRequest;
use App\Http\Requests\Api\UpdateRoutineStepRequest;
use App\Http\Resources\RoutineStepResource;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Support\ClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class RoutineStepController extends Controller
{
    public function store(StoreRoutineStepRequest $request, Team $team, Routine $routine): JsonResponse
    {
        $step = ClientUuid::firstOrCreate(
            $routine->steps()->withTrashed(),
            $request->validated('client_uuid'),
            fn (): RoutineStep => $routine->steps()->create($request->validated()),
        );

        return RoutineStepResource::make($step)
            ->response()
            ->setStatusCode($step->wasRecentlyCreated ? 201 : 200);
    }

    public function update(UpdateRoutineStepRequest $request, Team $team, Routine $routine, RoutineStep $step): RoutineStepResource
    {
        $step->update($request->validated());

        return RoutineStepResource::make($step);
    }

    public function destroy(Team $team, Routine $routine, RoutineStep $step): Response
    {
        Gate::authorize('update', $routine);

        $step->delete();

        return response()->noContent();
    }
}
