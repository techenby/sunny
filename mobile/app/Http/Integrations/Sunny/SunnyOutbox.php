<?php

namespace App\Http\Integrations\Sunny;

use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
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
        $this->ensureRecordInTeam($resource, $teamId, $id);
        $model = SunnyStore::model($resource);
        if ($owner = SunnyStore::owner($resource)) {
            [$ownerType, $ownerKey] = $owner;
            $payload[$ownerKey] = $id === null ? ($payload[$ownerKey] ?? null) : $model::query()->whereKey($id)->value($ownerKey);
            if (! SunnyStore::inTeam($ownerType, $teamId)->whereKey($payload[$ownerKey])->exists()) {
                $noun = $ownerType === 'checklists' ? 'list' : 'routine';
                throw ValidationException::withMessages([$ownerKey => "This {$noun} is no longer available. Sync with Sunny."]);
            }
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
            $record = $id === null ? $this->newRecord($resource, $teamId, $payload) : $model::query()->findOrFail($id);
            $record->fill([...Arr::only($payload, SunnyStore::editableFields($resource)), 'updated_at' => now()]);
            if ($photoPath !== null || ($payload['remove_photo'] ?? false)) {
                $record->photo_url = $photoPath;
            }
            if (array_key_exists('completed', $payload)) {
                $record->completed_at = $payload['completed'] ? ($record->completed_at ?? now()) : null;
                $record->completed_by = $payload['completed'] ? $record->completed_by : null;
            }
            $record->save();

            if ($resource === 'routines' && $id !== null) {
                RoutineOccurrence::query()->where('routine_id', $id)->whereDate('due_on', '>=', today())->update(Arr::only($record->getAttributes(), ['name', 'time_of_day']));
            }

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

    public function delete(string $resource, int $teamId, int $id): void
    {
        $this->ensureRecordInTeam($resource, $teamId, $id);

        DB::transaction(function () use ($resource, $teamId, $id): void {
            if ($id < 0) {
                $this->discard($resource, $id);

                return;
            }

            $record = SunnyStore::model($resource)::query()->findOrFail($id);
            $ownerKey = SunnyStore::owner($resource)[1] ?? null;
            $write = PendingWrite::query()->for($resource, $id)->first() ?? new PendingWrite([
                'server' => SunnyStore::server(), 'resource' => $resource, 'record_id' => $id, 'team_id' => $teamId, 'version' => 0,
            ]);
            $write->fill([
                'payload' => $ownerKey !== null ? [$ownerKey => $record->{$ownerKey}] : [],
                'client_uuid' => null,
                'photo_path' => null,
                'deletes' => true,
                'version' => $write->version + 1,
                'error' => null,
            ])->save();
            $record->delete();

            if (SunnyStore::children($resource) !== null) {
                $this->forgetChildren($resource, $id);
            }

            if ($resource === 'items') {
                Item::query()->forCurrentServer()->where('parent_id', $id)->update(['parent_id' => null]);
                $this->rewritePendingReference($resource, 'parent_id', $id, null);
            }

            if ($resource === 'routines') {
                $this->forgetRoutineOccurrences($id);
            }
        });
    }

    public function queueRoutineStep(int $id, bool $completed): void
    {
        DB::transaction(function () use ($id, $completed): void {
            $step = RoutineOccurrenceStep::forCurrentServer()->with('occurrence')->find($id);
            if ($step === null) {
                throw ValidationException::withMessages(['step' => 'This step is no longer available. Sync with Sunny.']);
            }
            $step->update(['completed_at' => $completed ? now() : null]);

            $write = PendingWrite::query()->for('routine_occurrence_steps', $id)->first() ?? new PendingWrite([
                'server' => SunnyStore::server(), 'resource' => 'routine_occurrence_steps', 'record_id' => $id,
                'team_id' => $step->occurrence->team_id, 'payload' => [], 'version' => 0,
            ]);
            $write->fill([
                'payload' => ['routine_occurrence_id' => $step->routine_occurrence_id, 'completed' => $completed],
                'version' => $write->version + 1,
                'error' => null,
            ])->save();
        });
    }

    /**
     * Throw away a record's queued change. A record Sunny has never seen goes with it: anything inside it moves to the top level, and a list's items are dropped.
     */
    public function discard(string $resource, int $id): void
    {
        DB::transaction(function () use ($resource, $id): void {
            PendingWrite::query()->for($resource, $id)->delete();

            if ($id >= 0) {
                return;
            }

            SunnyStore::model($resource)::query()->whereKey($id)->delete();

            if (SunnyStore::children($resource) !== null) {
                $this->forgetChildren($resource, $id);
            } elseif (SunnyStore::owner($resource) === null) {
                SunnyStore::model($resource)::query()->where('parent_id', $id)->update(['parent_id' => null]);
                $this->rewritePendingReference($resource, 'parent_id', $id, null);
            }
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
        PendingWrite::query()->for($resource, $localId)->update(['record_id' => $serverId]);

        if ($children = SunnyStore::children($resource)) {
            [$childType, $childKey] = $children;
            SunnyStore::model($childType)::query()->where($childKey, $localId)->update([$childKey => $serverId]);
            $this->rewritePendingReference($childType, $childKey, $localId, $serverId);
        } elseif (SunnyStore::owner($resource) === null) {
            $model::query()->where('parent_id', $localId)->update(['parent_id' => $serverId]);
            $this->rewritePendingReference($resource, 'parent_id', $localId, $serverId);
        }
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

    private function ensureRecordInTeam(string $resource, int $teamId, ?int $id): void
    {
        throw_unless(in_array($resource, ['recipes', 'items', 'checklists', 'checklist_items', 'routines', 'routine_steps'], true), UnexpectedValueException::class);
        if (! Team::query()->where('server', SunnyStore::server())->whereKey($teamId)->exists()) {
            throw ValidationException::withMessages(['team' => 'Sync with Sunny before saving to this team.']);
        }
        if ($id !== null && ! SunnyStore::inTeam($resource, $teamId)->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['record' => 'This record is no longer available. Sync with Sunny.']);
        }
    }

    private function newRecord(string $resource, int $teamId, array $payload): Model
    {
        $model = SunnyStore::model($resource);
        $record = new $model(['id' => $this->nextLocalId($model), 'server' => SunnyStore::server(), 'created_at' => now()]);

        if ($owner = SunnyStore::owner($resource)) {
            $record->position = (int) $model::query()->where($owner[1], $payload[$owner[1]])->max('position') + 1;
        } else {
            $record->team_id = $teamId;
        }

        return $record;
    }

    private function forgetChildren(string $resource, int $id): void
    {
        [$childType, $childKey] = SunnyStore::children($resource);
        $model = SunnyStore::model($childType);
        $ids = $model::query()->where($childKey, $id)->pluck('id');

        PendingWrite::query()->forCurrentServer()->where('resource', $childType)->get()
            ->filter(fn (PendingWrite $write): bool => $ids->contains($write->record_id) || ($write->payload[$childKey] ?? null) === $id)
            ->each->delete();
        $model::query()->whereKey($ids)->delete();
    }

    private function forgetRoutineOccurrences(int $routineId): void
    {
        $occurrenceIds = RoutineOccurrence::query()->where('routine_id', $routineId)->pluck('id');
        $stepIds = RoutineOccurrenceStep::query()->whereIn('routine_occurrence_id', $occurrenceIds)->pluck('id');

        PendingWrite::query()->forCurrentServer()->where('resource', 'routine_occurrence_steps')->whereIn('record_id', $stepIds)->delete();
        RoutineOccurrenceStep::query()->whereKey($stepIds)->delete();
        RoutineOccurrence::query()->whereKey($occurrenceIds)->delete();
    }

    private function rewritePendingReference(string $resource, string $key, int $from, ?int $to): void
    {
        PendingWrite::query()->forCurrentServer()->where('resource', $resource)->get()
            ->filter(fn (PendingWrite $write): bool => ($write->payload[$key] ?? null) === $from)
            ->each(fn (PendingWrite $write) => $write->update(['payload' => [...$write->payload, $key => $to]]));
    }
}
