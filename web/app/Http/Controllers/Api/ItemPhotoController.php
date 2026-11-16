<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Inventory\AddItemPhoto;
use App\Actions\Inventory\MoveItemPhoto;
use App\Actions\Inventory\RemoveItemPhoto;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreItemPhotoRequest;
use App\Http\Requests\Api\UpdateItemPhotoRequest;
use App\Http\Resources\ItemPhotoResource;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Team;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ItemPhotoController extends Controller
{
    public function store(StoreItemPhotoRequest $request, Team $team, Item $item, AddItemPhoto $action): JsonResponse
    {
        if ($existing = $request->existingPhoto()) {
            return ItemPhotoResource::make($existing)->response();
        }

        $photo = $action->handle($item, $request->file('photo'), $request->validated('position'), $request->validated('client_uuid'));

        return ItemPhotoResource::make($photo)
            ->response()
            ->setStatusCode(201);
    }

    public function update(UpdateItemPhotoRequest $request, Team $team, Item $item, ItemPhoto $photo, MoveItemPhoto $action): ItemPhotoResource
    {
        return ItemPhotoResource::make($action->handle($photo, $request->validated('position'))->refresh());
    }

    public function destroy(Team $team, Item $item, ItemPhoto $photo, RemoveItemPhoto $action): Response
    {
        Gate::authorize('update', $item);

        $action->handle($photo);

        return response()->noContent();
    }
}
