<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\CreateItem;
use App\Actions\Inventory\DuplicateItem;
use App\Actions\Inventory\ForceDeleteItem;
use App\Actions\Inventory\MoveItemToTeam;
use App\Actions\Inventory\RestoreItem;
use App\Actions\Inventory\UpdateItem;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\DuplicateItemRequest;
use App\Http\Requests\Api\MoveItemRequest;
use App\Http\Requests\Api\StoreItemRequest;
use App\Http\Requests\Api\UpdateItemRequest;
use App\Http\Resources\ItemResource;
use App\Models\Item;
use App\Models\Team;
use App\Support\ClientUuid;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ItemController extends Controller
{
    /** @param  ModelNotFoundException<Model>  $exception */
    public static function forceDestroyMissing(Request $request, ModelNotFoundException $exception): Response
    {
        $team = $request->route('team');

        throw_unless(
            $exception->getModel() === Item::class
                && $team instanceof Team
                && $request->user()?->belongsToTeam($team)
                && Item::withTrashed()->whereKey($exception->getIds())->doesntExist(),
            $exception,
        );

        return response()->noContent();
    }

    public function index(Team $team): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', Item::class);

        $items = $team->items()->with('photos')->latest()->get();

        return ItemResource::collection($items);
    }

    public function store(StoreItemRequest $request, Team $team, CreateItem $action): JsonResponse
    {
        $item = ClientUuid::firstOrCreate(
            $team->items()->withTrashed(),
            $request->validated('client_uuid'),
            fn (): Item => $action->handle($team, $request->validated()),
        );

        return ItemResource::make($item)
            ->response()
            ->setStatusCode($item->wasRecentlyCreated ? 201 : 200);
    }

    public function duplicate(DuplicateItemRequest $request, Team $team, Item $item, DuplicateItem $action): JsonResponse
    {
        $copies = $action->handle($item, $request->validated('count') ?? 1, $request->validated('client_uuid'));

        return ItemResource::collection($copies)
            ->response()
            ->setStatusCode($copies->first()?->wasRecentlyCreated ? 201 : 200);
    }

    public function move(MoveItemRequest $request, Team $team, Item $item, MoveItemToTeam $action): ItemResource
    {
        $movedItemIds = [$item->id, ...$item->descendantIds()];

        $item = $action->handle($item, Team::findOrFail($request->integer('team_id')));

        return ItemResource::make($item)->additional(['moved_item_ids' => $movedItemIds]);
    }

    public function show(Team $team, Item $item): ItemResource
    {
        Gate::authorize('view', $item);

        return ItemResource::make($item);
    }

    public function update(UpdateItemRequest $request, Team $team, Item $item, UpdateItem $action): ItemResource
    {
        $item = $action->handle($item, $request->safe()->except('remove_photo'), $request->boolean('remove_photo'));

        return ItemResource::make($item);
    }

    public function destroy(Team $team, Item $item): Response
    {
        Gate::authorize('delete', $item);

        $item->purge();

        return response()->noContent();
    }

    public function restore(Team $team, Item $item, RestoreItem $action): ItemResource
    {
        Gate::authorize('restore', $item);

        return ItemResource::make($action->handle($item));
    }

    public function forceDestroy(Team $team, Item $item, ForceDeleteItem $action): Response
    {
        Gate::authorize('forceDelete', $item);

        abort_unless($item->trashed(), 404);

        $action->handle($item);

        return response()->noContent();
    }
}
