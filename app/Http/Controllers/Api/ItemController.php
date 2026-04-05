<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\CreateItem;
use App\Actions\Inventory\UpdateItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreItemRequest;
use App\Http\Requests\Api\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Item::class);

        $items = Auth::user()->currentTeam->items()->latest()->get();

        return ItemResource::collection($items);
    }

    public function store(StoreItemRequest $request, CreateItem $action): JsonResponse
    {
        $item = $action->handle(Auth::user()->currentTeam, $request->validated());

        return ItemResource::make($item)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Item $item): ItemResource
    {
        Gate::authorize('view', $item);

        return ItemResource::make($item);
    }

    public function update(UpdateItemRequest $request, Item $item, UpdateItem $action): ItemResource
    {
        $item = $action->handle($item, $request->validated());

        return ItemResource::make($item);
    }

    public function destroy(Item $item): Response
    {
        Gate::authorize('delete', $item);

        $item->purge();

        return response()->noContent();
    }
}
