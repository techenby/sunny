<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class AddItemPhoto
{
    public function handle(Item $item, UploadedFile $photo, ?int $position = null, ?string $clientUuid = null): ItemPhoto
    {
        $filename = Str::slug($item->name) . '-' . $item->id . '-' . Str::lower(Str::random(8)) . '.' . $photo->extension();

        $path = $photo->storeAs("teams/{$item->team_id}/items", $filename);

        throw_if($path === false, RuntimeException::class, 'The photo could not be saved.');

        $thumbPath = (new CreateThumbnail)->handle($path);

        $saved = DB::transaction(function () use ($item, $path, $thumbPath, $clientUuid): ?ItemPhoto {
            Item::withTrashed()->whereKey($item->id)->lockForUpdate()->first();

            $existing = filled($clientUuid) ? $item->photos()->firstWhere('client_uuid', $clientUuid) : null;

            if ($existing !== null) {
                return $existing;
            }

            $count = $item->photos()->count();

            if ($count >= ItemPhoto::MAX_PER_ITEM) {
                return null;
            }

            return $item->photos()->create([
                'path' => $path,
                'thumb_path' => $thumbPath,
                'position' => $count,
                'client_uuid' => $clientUuid,
            ]);
        });

        if ($saved === null) {
            Storage::delete(array_filter([$path, $thumbPath]));

            throw ValidationException::withMessages([
                'photo' => __('An item can have up to :count photos.', ['count' => ItemPhoto::MAX_PER_ITEM]),
            ]);
        }

        if (! $saved->wasRecentlyCreated) {
            Storage::delete(array_filter([$path, $thumbPath]));

            return $saved;
        }

        if ($position !== null) {
            (new MoveItemPhoto)->handle($saved, $position);
        }

        return $saved->refresh();
    }
}
