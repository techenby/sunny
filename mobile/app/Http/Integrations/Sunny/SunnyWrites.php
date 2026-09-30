<?php

namespace App\Http\Integrations\Sunny;

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\UpdateRoutineStepRequest;
use App\Models\PendingWrite;
use App\Models\RoutineOccurrenceStep;
use App\Models\Team;
use Illuminate\Support\Facades\DB;
use Saloon\Exceptions\Request\RequestException;
use UnexpectedValueException;

class SunnyWrites
{
    public function __construct(
        private readonly SunnyAuth $auth,
        private readonly SunnyStore $store,
        private readonly SunnyOutbox $outbox,
    ) {}

    /**
     * Send every queued change Sunny hasn't refused. A new item waits until the item it's inside has been created.
     * Pass the token when pushing off the UI thread, where secure storage is not available.
     */
    public function push(#[\SensitiveParameter] ?string $token = null): void
    {
        $connector = null;

        try {
            do {
                $sent = false;

                foreach (PendingWrite::query()->forCurrentServer()->whereNull('error')->orderBy('id')->get() as $write) {
                    if (($write->payload['parent_id'] ?? 0) < 0) {
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

    private function send(PendingWrite $write, SunnyConnector $connector): void
    {
        $team = Team::query()->where('server', SunnyStore::server())->find($write->team_id);
        if (! $team?->slug) {
            $write->update(['error' => 'Sync with Sunny before saving to this team.']);

            return;
        }

        if ($write->resource === 'routine_occurrence_steps') {
            $this->sendRoutineStep($write, $team, $connector);

            return;
        }

        $photoPath = $write->photo_path !== null && is_file($write->photo_path) ? $write->photo_path : null;
        $payload = $write->isCreate() ? [...$write->payload, 'client_uuid' => $write->client_uuid] : $write->payload;

        try {
            $record = $connector->send(new SaveRecordRequest($team->slug, $write->resource, $payload, $write->isCreate() ? null : $write->record_id, $photoPath))->json('data');
        } catch (RequestException $exception) {
            $message = $this->rejectionMessage($exception);
            throw_if($message === null, $exception);
            $write->update(['error' => $message]);

            return;
        }

        if (! is_array($record) || ! is_int($record['id'] ?? null) || $record['id'] <= 0
            || ($record['team_id'] ?? null) !== $write->team_id || (! $write->isCreate() && $record['id'] !== $write->record_id)) {
            report(new UnexpectedValueException('Sunny returned an invalid saved record.'));
            $write->update(['error' => 'Sunny didn’t confirm this change. Edit it to try again.']);

            return;
        }

        DB::transaction(function () use ($write, $record, $photoPath): void {
            if ($write->isCreate()) {
                $this->outbox->adoptServerId($write->resource, $write->record_id, $record['id']);
            }

            $current = PendingWrite::query()->for($write->resource, $record['id'])->first();

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

    /**
     * Why Sunny refused a change for good, or null when it's worth sending again later.
     */
    private function rejectionMessage(RequestException $exception): ?string
    {
        $response = $exception->getResponse();

        return match ($response->status()) {
            403 => 'You no longer have permission to save to this team.',
            404 => 'This record or team is no longer on Sunny.',
            413 => 'The photo is too large to upload.',
            422 => collect($response->json('errors') ?? [])->flatten()->first() ?? 'Sunny couldn’t accept this change.',
            default => null,
        };
    }
}
