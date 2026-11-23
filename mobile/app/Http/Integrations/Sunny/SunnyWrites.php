<?php

namespace App\Http\Integrations\Sunny;

use App\Http\Integrations\Sunny\Requests\ChecklistActionRequest;
use App\Http\Integrations\Sunny\Requests\DeleteRecordRequest;
use App\Http\Integrations\Sunny\Requests\ForceDeleteItemRequest;
use App\Http\Integrations\Sunny\Requests\RestoreItemRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\UpdateRoutineStepRequest;
use App\Models\PendingWrite;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Request;
use UnexpectedValueException;

class SunnyWrites
{
    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
        private readonly SunnyOutbox $outbox,
    ) {}

    /**
     * Send every queued change Sunny hasn't refused. A new item waits until the item or list it's inside has been created.
     * Pass the token when pushing off the UI thread, where secure storage is not available.
     */
    public function push(#[\SensitiveParameter] ?string $token = null): void
    {
        $connector = null;

        try {
            do {
                $sent = false;

                foreach (PendingWrite::query()->forCurrentServer()->whereNull('error')->orderBy('id')->get() as $write) {
                    if (($write->payload['parent_id'] ?? 0) < 0 || ($write->payload['checklist_id'] ?? 0) < 0 || ($write->payload['routine_id'] ?? 0) < 0 || ($write->payload['item_id'] ?? 0) < 0) {
                        continue;
                    }

                    if (! $this->orderIsReady($write)) {
                        continue;
                    }

                    $this->send($write, $connector ??= $this->auth->authenticatedConnector($token));
                    $sent = true;
                }
            } while ($sent);
        } finally {
            $this->outbox->prunePhotos();
        }
    }

    private function orderIsReady(PendingWrite $write): bool
    {
        [$key, $childType] = match ($write->resource) {
            'items' => ['photo_order', 'item_photos'],
            'routines' => ['step_order', 'routine_steps'],
            default => [null, null],
        };
        $order = $key !== null ? ($write->payload[$key] ?? null) : null;

        if (! is_array($order) || ($key === 'step_order' && $write->isCreate())) {
            return true;
        }

        $creating = PendingWrite::query()->forCurrentServer()->where('resource', $childType)->whereNull('error')->where('deletes', false)
            ->whereIn('record_id', array_filter($order, fn (int $id): bool => $id < 0))
            ->exists();

        if ($creating) {
            return false;
        }

        $kept = array_values(array_filter($order, fn (int $id): bool => $id > 0));

        if ($kept !== $order) {
            $write->update(['payload' => [...$write->payload, $key => $kept]]);
        }

        return true;
    }

    private function send(PendingWrite $write, SunnyConnector $connector): void
    {
        $team = Team::query()->where('server', SunnyStore::server())->find($write->team_id);
        if (! $team?->slug) {
            $write->update(['error' => SunnySyncCoordinator::REFRESH_ADVICE]);

            return;
        }

        if ($write->resource === 'routine_occurrence_steps') {
            $this->sendRoutineStep($write, $team, $connector);

            return;
        }

        if (in_array($write->resource, ['checklist_clears', 'checklist_resets'], true)) {
            $this->sendChecklistAction($write, $team, $connector);

            return;
        }

        $owner = SunnyStore::owner($write->resource);
        $collection = $owner !== null ? $owner[0].'/'.$write->payload[$owner[1]].'/'.$owner[2] : $write->resource;

        if ($write->resource === 'items' && ($write->payload['restore'] ?? false)) {
            $this->sendRestore($write, $connector, new RestoreItemRequest($team->slug, $write->record_id));

            return;
        }

        if ($write->deletes) {
            $this->sendDelete($write, $connector, ...match (true) {
                ! ($write->payload['forever'] ?? false) => [new DeleteRecordRequest($team->slug, $collection, $write->record_id)],
                $write->payload['delete_first'] ?? false => [new DeleteRecordRequest($team->slug, $collection, $write->record_id), new ForceDeleteItemRequest($team->slug, $write->record_id)],
                default => [new ForceDeleteItemRequest($team->slug, $write->record_id)],
            });

            return;
        }

        $photoPath = $write->photo_path !== null && is_file($write->photo_path) ? $write->photo_path : null;
        $payload = $owner !== null ? Arr::except($write->payload, $owner[1]) : $write->payload;
        $payload = $write->isCreate() ? [...Arr::except($payload, 'step_order'), 'client_uuid' => $write->client_uuid] : $payload;
        [$ownerKey, $ownerId] = $owner !== null ? [$owner[1], $write->payload[$owner[1]]] : ['team_id', $write->team_id];

        try {
            $record = $connector->send(new SaveRecordRequest($team->slug, $collection, $payload, $write->isCreate() ? null : $write->record_id, $photoPath))->json('data');
        } catch (RequestException $exception) {
            $message = $this->rejectionMessage($exception);
            throw_if($message === null, $exception);
            $write->update(['error' => $message]);

            return;
        }

        if (! is_array($record) || ! is_int($record['id'] ?? null) || $record['id'] <= 0
            || ($record[$ownerKey] ?? null) !== $ownerId || (! $write->isCreate() && $record['id'] !== $write->record_id)) {
            report(new UnexpectedValueException('Sunny returned an invalid saved record.'));
            $write->update(['error' => 'The server didn’t confirm this change. Edit it to try again.']);

            return;
        }

        DB::transaction(function () use ($write, $record, $photoPath): void {
            if ($write->isCreate()) {
                $this->outbox->adoptServerId($write->resource, $write->record_id, $record['id']);
            }

            $current = PendingWrite::query()->for($write->resource, $record['id'])->first();

            if ($current !== null && $current->version === $write->version && $write->isCreate() && isset($write->payload['step_order'])) {
                $current->update(['payload' => Arr::only($current->payload, 'step_order'), 'client_uuid' => null]);
                $this->store->saveRecord($write->resource, $record);

                return;
            }

            if ($current === null || $current->version === $write->version) {
                $current?->delete();
                $this->store->saveRecord($write->resource, $record);

                return;
            }

            $current->update([
                'client_uuid' => null,
                'photo_path' => $current->photo_path === $photoPath ? null : $current->photo_path,
            ]);
        });
    }

    private function sendDelete(PendingWrite $write, SunnyConnector $connector, Request ...$requests): void
    {
        foreach ($requests as $request) {
            try {
                $connector->send($request);
            } catch (RequestException $exception) {
                throw_if($this->rejectionMessage($exception) === null, $exception);
            }
        }

        PendingWrite::query()->whereKey($write->id)->where('version', $write->version)->delete();
    }

    private function sendRestore(PendingWrite $write, SunnyConnector $connector, RestoreItemRequest $request): void
    {
        try {
            $record = $connector->send($request)->json('data');
        } catch (RequestException $exception) {
            throw_if($this->rejectionMessage($exception) === null, $exception);
            $record = null;
        }

        DB::transaction(function () use ($write, $record): void {
            $current = PendingWrite::query()->whereKey($write->id)->where('version', $write->version)->first();

            if ($current === null) {
                return;
            }

            $edits = Arr::except($current->payload, 'restore');

            if ($edits !== []) {
                $current->update(['payload' => $edits]);

                return;
            }

            $current->delete();

            if (is_array($record) && ($record['id'] ?? null) === $write->record_id && ($record['team_id'] ?? null) === $write->team_id) {
                $this->store->saveRecord('items', $record);
            }
        });
    }

    private function sendRoutineStep(PendingWrite $write, Team $team, SunnyConnector $connector): void
    {
        try {
            $step = $connector->send(new UpdateRoutineStepRequest($team->slug, $write->payload['routine_occurrence_id'], $write->record_id, $write->payload['completed']))->json('data');
        } catch (RequestException $exception) {
            throw_if($this->rejectionMessage($exception) === null, $exception);
            PendingWrite::query()->whereKey($write->id)->where('version', $write->version)->delete();

            return;
        }

        DB::transaction(function () use ($write, $step): void {
            if (! PendingWrite::query()->whereKey($write->id)->where('version', $write->version)->delete()) {
                return;
            }

            if (is_array($step) && ($step['id'] ?? null) === $write->record_id) {
                RoutineOccurrenceStep::query()->find($write->record_id)?->update(['completed_at' => $step['completed_at'] ?? null]);
            }
        });
    }

    private function sendChecklistAction(PendingWrite $write, Team $team, SunnyConnector $connector): void
    {
        $action = $write->resource === 'checklist_clears' ? 'clear-completed' : 'reset';

        try {
            $connector->send(new ChecklistActionRequest($team->slug, $write->record_id, $action));
        } catch (RequestException $exception) {
            throw_if($this->rejectionMessage($exception) === null, $exception);
        }

        $write->delete();
    }

    /**
     * Why Sunny refused a change for good, or null when it's worth sending again later.
     */
    private function rejectionMessage(RequestException $exception): ?string
    {
        $response = $exception->getResponse();

        return match ($response->status()) {
            403 => 'You no longer have permission to save to this team.',
            404 => 'This record or team is no longer on the server.',
            413 => 'The photo is too large to upload.',
            422 => collect($response->json('errors') ?? [])->flatten()->first() ?? 'The server couldn’t accept this change.',
            default => null,
        };
    }
}
