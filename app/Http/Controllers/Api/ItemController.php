<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\CreateItem;
use App\Actions\Inventory\DuplicateItem;
use App\Actions\Inventory\UpdateItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DuplicateItemRequest;
use App\Http\Requests\Api\StoreItemRequest;
use App\Http\Requests\Api\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Item::class);

        $items = $team->items()->latest()->get();

        return ItemResource::collection($items);
    }

    public function store(StoreItemRequest $request, Team $team, CreateItem $action): JsonResponse
    {
        $item = $action->handle($team, $request->validated());

        return ItemResource::make($item)
            ->response()
            ->setStatusCode(201);
    }

    public function duplicate(DuplicateItemRequest $request, Team $team, Item $item, DuplicateItem $action): JsonResponse
    {
        $copies = $action->handle($item, $request->validated('count') ?? 1);

        return ItemResource::collection($copies)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Team $team, Item $item): ItemResource
    {
        Gate::authorize('view', $item);

        return ItemResource::make($item);
    }

    public function update(UpdateItemRequest $request, Team $team, Item $item, UpdateItem $action): ItemResource
    {
        $item = $action->handle($item, $request->validated());

        return ItemResource::make($item);
    }

    public function destroy(Team $team, Item $item): Response
    {
        Gate::authorize('delete', $item);

        $item->purge();

        return response()->noContent();
    }
}
