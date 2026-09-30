<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChecklistRequest;
use App\Http\Requests\Api\UpdateChecklistRequest;
use App\Http\Resources\ChecklistResource;
use App\Models\Checklist;
use App\Models\Team;
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
        $existing = $request->filled('client_uuid')
            ? $team->checklists()->withTrashed()->firstWhere('client_uuid', $request->validated('client_uuid'))
            : null;

        return ChecklistResource::make($existing ?? $team->checklists()->create($request->validated()))
            ->response()
            ->setStatusCode($existing ? 200 : 201);
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

    public function destroy(Team $team, Checklist $checklist): Response
    {
        Gate::authorize('delete', $checklist);

        $checklist->delete();

        return response()->noContent();
    }
}
