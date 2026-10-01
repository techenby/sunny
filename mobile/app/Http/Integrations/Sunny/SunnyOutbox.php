<?php

namespace App\Http\Integrations\Sunny;

use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\Team;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
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
     * Apply a routine save to the local routine and its steps straight away and queue it for Sunny, replacing any change still waiting for that routine.
     * The payload's steps are the routine's whole ordered list; steps without a known id are new.
     *
     * @param  array{name: string, time_of_day: string, frequency: string, weekdays: list<int>|null, day_of_month: int|null, is_active: bool, steps: list<array{id?: int|null, name: string}>}  $payload
     * @return int The local routine's id, negative until Sunny confirms a new routine.
     */
    public function queueRoutine(int $teamId, array $payload, ?int $id = null): int
    {
        if (! Team::query()->where('server', SunnyStore::server())->whereKey($teamId)->exists()) {
            throw ValidationException::withMessages(['team' => 'Sync with Sunny before saving to this team.']);
        }
        if ($id !== null && ! Routine::forCurrentServer()->where('team_id', $teamId)->whereKey($id)->exists()) {
            throw ValidationException::withMessages(['record' => 'This routine is no longer available. Sync with Sunny.']);
        }

        return DB::transaction(function () use ($teamId, $payload, $id): int {
            $routine = $id === null
                ? new Routine(['id' => $this->nextLocalId(Routine::class), 'server' => SunnyStore::server(), 'team_id' => $teamId])
                : Routine::query()->findOrFail($id);
            $routine->fill(Arr::only($payload, ['name', 'time_of_day', 'frequency', 'weekdays', 'day_of_month', 'is_active']))->save();

            $known = RoutineStep::query()->where('routine_id', $routine->id)->pluck('id');
            $nextStepId = min(0, (int) RoutineStep::query()->min('id')) - 1;
            $steps = collect($payload['steps'])->values()->map(function (array $step) use ($known, &$nextStepId): array {
                return [
                    'id' => $known->contains($step['id'] ?? null) ? $step['id'] : $nextStepId--,
                    'name' => $step['name'],
                ];
            });

            RoutineStep::query()->where('routine_id', $routine->id)->delete();
            foreach ($steps as $index => $step) {
                RoutineStep::query()->create([...$step, 'server' => SunnyStore::server(), 'routine_id' => $routine->id, 'position' => $index + 1]);
            }

            $write = PendingWrite::query()->for('routines', $routine->id)->first() ?? new PendingWrite([
                'server' => SunnyStore::server(), 'resource' => 'routines', 'record_id' => $routine->id, 'team_id' => $teamId,
                'client_uuid' => $id === null ? (string) Str::uuid() : null, 'payload' => [], 'version' => 0,
            ]);
            $write->fill([
                'payload' => [...Arr::except($payload, 'steps'), 'steps' => $steps->all()],
                'version' => $write->version + 1,
                'error' => null,
            ])->save();

            return $routine->id;
        });
    }

    /**
     * Queue a routine's deletion. The routine stays on this phone, without today's occurrences, until Sunny confirms. One Sunny has never seen is simply dropped.
     */
    public function queueRoutineDelete(int $id): void
    {
        $routine = Routine::forCurrentServer()->find($id);
        if ($routine === null) {
            throw ValidationException::withMessages(['record' => 'This routine is no longer available. Sync with Sunny.']);
        }

        if ($id < 0) {
            $this->discard('routines', $id);

            return;
        }

        DB::transaction(function () use ($routine): void {
            $occurrenceIds = RoutineOccurrence::query()->where('routine_id', $routine->id)->pluck('id');
            $stepIds = RoutineOccurrenceStep::query()->whereIn('routine_occurrence_id', $occurrenceIds)->pluck('id');
            PendingWrite::query()->forCurrentServer()->where('resource', 'routine_occurrence_steps')->whereIn('record_id', $stepIds)->delete();
            RoutineOccurrenceStep::query()->whereIn('id', $stepIds)->delete();
            RoutineOccurrence::query()->whereIn('id', $occurrenceIds)->delete();

            $write = PendingWrite::query()->for('routines', $routine->id)->first() ?? new PendingWrite([
                'server' => SunnyStore::server(), 'resource' => 'routines', 'record_id' => $routine->id, 'team_id' => $routine->team_id,
                'payload' => [], 'version' => 0,
            ]);
            $write->fill(['payload' => ['deleted' => true], 'version' => $write->version + 1, 'error' => null])->save();
        });
    }

    /**
     * The ids of routines whose deletion is waiting to reach Sunny.
     *
     * @return Collection<int, int>
     */
    public function pendingRoutineDeletes(): Collection
    {
        return PendingWrite::query()->forCurrentServer()->where('resource', 'routines')->get()
            ->filter(fn (PendingWrite $write): bool => $write->payload['deleted'] ?? false)
            ->pluck('record_id');
    }

    /**
     * Give steps Sunny has just created the ids it assigned, on the routine and in any change queued after the one that was sent.
     *
     * @param  array<int, int>  $serverIds  Local step id => server step id.
     */
    public function adoptRoutineStepIds(int $routineId, array $serverIds): void
    {
        foreach ($serverIds as $localId => $serverId) {
            RoutineStep::query()->where('routine_id', $routineId)->whereKey($localId)->update(['id' => $serverId]);
        }

        $write = PendingWrite::query()->for('routines', $routineId)->first();
        if ($write === null || ! isset($write->payload['steps'])) {
            return;
        }

        $write->update(['payload' => [...$write->payload, 'steps' => array_map(
            fn (array $step): array => [...$step, 'id' => $serverIds[$step['id']] ?? $step['id']],
            $write->payload['steps'],
        )]]);
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
     * Throw away a record's queued change. A record Sunny has never seen goes with it, and anything inside it moves to the top level.
     */
    public function discard(string $resource, int $id): void
    {
        DB::transaction(function () use ($resource, $id): void {
            PendingWrite::query()->for($resource, $id)->delete();

            if ($resource === 'routines') {
                if ($id < 0) {
                    Routine::query()->whereKey($id)->delete();
                    RoutineStep::query()->where('routine_id', $id)->delete();
                }

                return;
            }

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
        if ($resource === 'routines') {
            Routine::query()->whereKey($serverId)->delete();
            RoutineStep::query()->where('routine_id', $serverId)->delete();
            Routine::query()->whereKey($localId)->update(['id' => $serverId, 'local_id' => $localId]);
            RoutineStep::query()->where('routine_id', $localId)->update(['routine_id' => $serverId]);
            PendingWrite::query()->for($resource, $localId)->update(['record_id' => $serverId]);

            return;
        }

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
