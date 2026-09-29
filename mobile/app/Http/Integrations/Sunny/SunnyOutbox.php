<?php

namespace App\Http\Integrations\Sunny;

use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use UnexpectedValueException;

class SunnyOutbox
{
    /**
     * Apply a save to the local record straight away and queue it for Sunny, folding it into any change still waiting for that record.
     *
     * @return int The local record's id, negative until Sunny confirms a new record.
     */
    public function queue(string $resource, int $teamId, array $payload, ?int $id = null, ?string $photoPath = null): int
    {
        throw_unless(in_array($resource, ['recipes', 'items'], true), UnexpectedValueException::class);
        if (! Team::query()->where('server', SunnyStore::server())->whereKey($teamId)->exists()) {
            throw ValidationException::withMessages(['team' => 'Sync with Sunny before saving to this team.']);
        }
        $model = SunnyStore::model($resource);
        if ($id !== null && ! $model::forCurrentServer()->where('team_id', $teamId)->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['record' => 'This record is no longer available. Sync with Sunny.']);
        }
        if ($resource === 'items' && ($payload['parent_id'] ?? null) !== null) {
            if (! Item::forCurrentServer()->where('team_id', $teamId)->whereKey($payload['parent_id'])->exists()) {
                throw ValidationException::withMessages(['parent_id' => 'Choose a parent in the same team.']);
            }
        }
        if ($photoPath !== null) {
            if (! is_file($photoPath) || ! is_readable($photoPath)) {
                throw ValidationException::withMessages(['photo' => 'Choose the photo again; its file is no longer available.']);
            }
            Validator::make(['photo' => new UploadedFile($photoPath, basename($photoPath), test: true)], [
                'photo' => ['image', 'max:10240'],
            ])->validate();
            $photoPath = $this->keepPhoto($photoPath);
        }

        return DB::transaction(function () use ($resource, $teamId, $payload, $id, $photoPath, $model): int {
            $record = $id === null
                ? new $model(['id' => $this->nextLocalId($model), 'server' => SunnyStore::server(), 'team_id' => $teamId, 'created_at' => now()])
                : $model::query()->findOrFail($id);
            $record->fill([...Arr::only($payload, SunnyStore::editableFields($resource)), 'updated_at' => now()]);
            if ($photoPath !== null || ($payload['remove_photo'] ?? false)) {
                $record->photo_url = $photoPath;
            }
            $record->save();

            $write = PendingWrite::query()->for($resource, $record->id)->first() ?? new PendingWrite([
                'server' => SunnyStore::server(), 'resource' => $resource, 'record_id' => $record->id, 'team_id' => $teamId,
                'client_uuid' => $id === null ? (string) Str::uuid() : null, 'payload' => [], 'version' => 0,
            ]);
            $removePhoto = $photoPath === null && (($write->payload['remove_photo'] ?? false) || ($payload['remove_photo'] ?? false));
            $write->fill([
                'payload' => [...$write->payload, ...$payload, ...(array_key_exists('remove_photo', $payload) ? ['remove_photo' => $removePhoto] : [])],
                'photo_path' => $photoPath ?? ($removePhoto ? null : $write->photo_path),
                'version' => $write->version + 1,
                'error' => null,
            ])->save();

            return $record->id;
        });
    }

    /**
     * Throw away a record's queued change. A record Sunny has never seen goes with it, and anything inside it moves to the top level.
     */
    public function discard(string $resource, int $id): void
    {
        DB::transaction(function () use ($resource, $id): void {
            PendingWrite::query()->for($resource, $id)->delete();

            if ($id >= 0) {
                return;
            }

            SunnyStore::model($resource)::query()->whereKey($id)->delete();
            SunnyStore::model($resource)::query()->where('parent_id', $id)->update(['parent_id' => null]);
            $this->rewritePendingParent($resource, $id, null);
        });
    }

    /**
     * Point a new record, its children, and anything queued for them at the id Sunny assigned it.
     */
    public function adoptServerId(string $resource, int $localId, int $serverId): void
    {
        $model = SunnyStore::model($resource);
        $model::query()->whereKey($serverId)->delete();
        $model::query()->whereKey($localId)->update(['id' => $serverId, 'local_id' => $localId]);
        $model::query()->where('parent_id', $localId)->update(['parent_id' => $serverId]);
        PendingWrite::query()->for($resource, $localId)->update(['record_id' => $serverId]);
        $this->rewritePendingParent($resource, $localId, $serverId);
    }

    /**
     * @return array{error: string|null}|null Null when nothing is waiting to reach Sunny.
     */
    public function status(string $resource, int $id): ?array
    {
        $write = PendingWrite::query()->for($resource, $id)->first();

        return $write === null ? null : ['error' => $write->error];
    }

    public function hasPending(): bool
    {
        return PendingWrite::query()->forCurrentServer()->whereNull('error')->exists();
    }

    /**
     * Delete kept photos that neither a queued change nor a local record still points at.
     */
    public function prunePhotos(): void
    {
        $inUse = PendingWrite::query()->pluck('photo_path')
            ->merge(Item::query()->pluck('photo_url'))
            ->merge(SunnyStore::model('recipes')::query()->pluck('photo_url'))
            ->filter()->flip();

        foreach (File::glob(self::photoDirectory().'/*') as $path) {
            if (! $inUse->has($path)) {
                File::delete($path);
            }
        }
    }

    public static function photoDirectory(): string
    {
        return storage_path('app/sunny-outbox');
    }

    /**
     * Copy the photo somewhere the system won't clear before it has been uploaded.
     */
    private function keepPhoto(string $path): string
    {
        File::ensureDirectoryExists(self::photoDirectory());
        $kept = self::photoDirectory().'/'.Str::uuid().'.'.(pathinfo($path, PATHINFO_EXTENSION) ?: 'jpg');
        File::copy($path, $kept);

        return $kept;
    }

    /**
     * @param  class-string<Model>  $model
     */
    private function nextLocalId(string $model): int
    {
        return min(0, (int) $model::query()->min('id'), (int) $model::query()->min('local_id')) - 1;
    }

    private function rewritePendingParent(string $resource, int $from, ?int $to): void
    {
        PendingWrite::query()->forCurrentServer()->where('resource', $resource)->get()
            ->filter(fn (PendingWrite $write): bool => ($write->payload['parent_id'] ?? null) === $from)
            ->each(fn (PendingWrite $write) => $write->update(['payload' => [...$write->payload, 'parent_id' => $to]]));
    }
}
