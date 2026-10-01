<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Enums\RoutineFrequency;
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

    public function store(StoreRoutineRequest $request, Team $team): JsonResponse
    {
        $existing = $request->filled('client_uuid')
            ? $team->routines()->withTrashed()->firstWhere('client_uuid', $request->validated('client_uuid'))
            : null;

        $routine = $existing ?? $team->routines()->create([
            ...$this->schedule($request->validated()),
            'starts_on' => $request->validated('starts_on') ?? $team->today()->toDateString(),
        ]);

        return RoutineResource::make($routine->load(['user', 'steps']))
            ->response()
            ->setStatusCode($existing ? 200 : 201);
    }

    public function show(Team $team, Routine $routine): RoutineResource
    {
        Gate::authorize('view', $routine);

        return RoutineResource::make($routine->load(['user', 'steps']));
    }

    public function update(UpdateRoutineRequest $request, Team $team, Routine $routine): RoutineResource
    {
        $routine->update($this->schedule($request->validated()));

        return RoutineResource::make($routine->load(['user', 'steps']));
    }

    public function destroy(Team $team, Routine $routine): Response
    {
        Gate::authorize('delete', $routine);

        $routine->delete();

        return response()->noContent();
    }

    /**
     * Only keep the fields the chosen frequency reads, so a routine switched
     * from weekly to daily doesn't hold on to stale weekdays.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function schedule(array $data): array
    {
        if (! isset($data['frequency'])) {
            return $data;
        }

        $frequency = RoutineFrequency::from($data['frequency']);

        return [
            ...$data,
            'weekdays' => $frequency->usesWeekdays() ? array_map(intval(...), $data['weekdays']) : null,
            'day_of_month' => $frequency->usesDayOfMonth() ? $data['day_of_month'] : null,
        ];
    }
}
