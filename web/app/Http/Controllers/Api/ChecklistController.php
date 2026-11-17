<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChecklistRequest;
use App\Http\Requests\Api\UpdateChecklistRequest;
use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Models\Team;
use App\Support\ClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ChecklistController extends Controller
{
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Checklist::class);

        $checklists = $team->checklists()->with('items')->orderBy('name')->get();

        return ChecklistResource::collection($checklists);
    }

    public function store(StoreChecklistRequest $request, Team $team): JsonResponse
    {
        $checklist = ClientUuid::firstOrCreate(
            $team->checklists()->withTrashed(),
            $request->validated('client_uuid'),
            fn (): Checklist => $team->checklists()->create($request->validated()),
        );

        return ChecklistResource::make($checklist)
            ->response()
            ->setStatusCode($checklist->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Team $team, Checklist $checklist): ChecklistResource
    {
        Gate::authorize('view', $checklist);

        return ChecklistResource::make($checklist->load('items'));
    }

    public function update(UpdateChecklistRequest $request, Team $team, Checklist $checklist): ChecklistResource
    {
        $checklist->update($request->validated());

        return ChecklistResource::make($checklist);
    }

    public function clearCompleted(Team $team, Checklist $checklist): ChecklistResource
    {
        Gate::authorize('update', $checklist);

        $checklist->clearCompleted();

        return ChecklistResource::make($checklist->load('items'));
    }

    public function reset(Team $team, Checklist $checklist): ChecklistResource
    {
        Gate::authorize('update', $checklist);

        $checklist->reset();

        return ChecklistResource::make($checklist->load('items'));
    }

    public function destroy(Team $team, Checklist $checklist): Response
    {
        Gate::authorize('delete', $checklist);

        $checklist->delete();

        return response()->noContent();
    }
}
