<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreChecklistItemRequest;
use App\Http\Requests\Api\UpdateChecklistItemRequest;
use App\Http\Resources\ChecklistItemResource;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Team;
use App\Support\ClientUuid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ChecklistItemController extends Controller
{
    public function index(Team $team, Checklist $checklist): AnonymousResourceCollection
    {
        Gate::authorize('view', $checklist);

        return ChecklistItemResource::collection($checklist->items);
    }

    public function store(StoreChecklistItemRequest $request, Team $team, Checklist $checklist): JsonResponse
    {
        $item = ClientUuid::firstOrCreate(
            $checklist->items(),
            $request->validated('client_uuid'),
            function () use ($request, $checklist): ChecklistItem {
                $item = $checklist->items()->create($request->safe()->except('completed'));

                if ($request->boolean('completed')) {
                    $item->complete($request->user());
                }

                return $item;
            },
        );

        return ChecklistItemResource::make($item)
            ->response()
            ->setStatusCode($item->wasRecentlyCreated ? 201 : 200);
    }

    public function show(Team $team, Checklist $checklist, ChecklistItem $item): ChecklistItemResource
    {
        Gate::authorize('view', $item);

        return ChecklistItemResource::make($item);
    }

    public function update(UpdateChecklistItemRequest $request, Team $team, Checklist $checklist, ChecklistItem $item): ChecklistItemResource
    {
        $item->update($request->safe()->except('completed'));

        if ($request->has('completed') && $request->boolean('completed') !== $item->isCompleted()) {
            $request->boolean('completed') ? $item->complete($request->user()) : $item->uncomplete();
        }

        return ChecklistItemResource::make($item);
    }

    public function destroy(Team $team, Checklist $checklist, ChecklistItem $item): Response
    {
        Gate::authorize('delete', $item);

        $item->delete();

        return response()->noContent();
    }
}
