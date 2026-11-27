<?php

declare(strict_types=1);

namespace App\Actions\Inventory;

use App\Actions\Photos\CreateThumbnail;
use App\Models\Item;
use App\Models\ItemPhoto;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Throwable;

class DuplicateItem
{
    /** @return Collection<int, Item> */
    public function handle(Item $item, int $count = 1, ?string $clientUuid = null): Collection
    {
        $clientUuids = $clientUuid === null
            ? []
            : Collection::times($count, fn (int $number): string => Uuid::uuid5($clientUuid, (string) $number)->toString())->all();

        $existing = $this->existingCopies($item, $clientUuids);

        if ($existing->isNotEmpty()) {
            return $existing;
        }

        try {
            return $this->copy($item, $count, $clientUuids);
        } catch (UniqueConstraintViolationException $exception) {
            $existing = $this->existingCopies($item, $clientUuids);

            return $existing->isNotEmpty() ? $existing : throw $exception;
        }
    }

    /**
     * @param  array<int, string>  $clientUuids
     * @return Collection<int, Item>
     */
    private function existingCopies(Item $item, array $clientUuids): Collection
    {
        if ($clientUuids === []) {
            return new Collection;
        }

        return Item::withTrashed()
            ->where('team_id', $item->team_id)
            ->whereIn('client_uuid', $clientUuids)
            ->get()
            ->sortBy(fn (Item $copy): int|false => array_search($copy->client_uuid, $clientUuids, true))
            ->values()
            ->toBase();
    }

    /**
     * @param  array<int, string>  $clientUuids
     * @return Collection<int, Item>
     */
    private function copy(Item $item, int $count, array $clientUuids): Collection
    {
        $photos = $item->photos()->get();
        $copiedPaths = [];

        try {
            return DB::transaction(function () use ($item, $count, $clientUuids, $photos, &$copiedPaths): Collection {
                return Collection::times($count, function (int $number) use ($item, $clientUuids, $photos, &$copiedPaths): Item {
                    $copy = $item->replicate(['client_uuid']);
                    $copy->client_uuid = $clientUuids[$number - 1] ?? null;
                    $copy->save();

                    $photos->each(function (ItemPhoto $photo) use ($copy, &$copiedPaths): void {
                        $extension = pathinfo($photo->path, PATHINFO_EXTENSION);
                        $path = "teams/{$copy->team_id}/items/" . Str::slug($copy->name) . '-' . $copy->id . '-' . Str::lower(Str::random(8)) . '.' . $extension;

                        Storage::copy($photo->path, $path);
                        $copiedPaths[] = $path;

                        $thumbPath = $photo->thumb_path ? (new CreateThumbnail)->copy($photo->thumb_path, $path) : null;
                        $copiedPaths[] = $thumbPath;

                        $copy->photos()->create([
                            'path' => $path,
                            'thumb_path' => $thumbPath,
                            'position' => $photo->position,
                        ]);
                    });

                    return $copy;
                });
            });
        } catch (Throwable $exception) {
            Storage::delete(array_values(array_filter($copiedPaths)));

            throw $exception;
        }
    }
}
