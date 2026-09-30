<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use Illuminate\Validation\ValidationException;
use Native\Mobile\Attributes\Computed;
use Throwable;

trait SavesSunnyRecord
{
    public bool $saving = false;

    public ?int $savedId = null;

    public ?int $recordTeamId = null;

    #[Computed]
    public function teamId(): ?int
    {
        return $this->recordTeamId;
    }

    protected function initializeTeam(?int $id = null): void
    {
        $this->recordTeamId = $id ?? app(SunnyTeam::class)->current()?->id;
    }

    protected function saveRecord(string $resource, array $payload, ?int $id = null): void
    {
        if ($this->saving || $this->savedId !== null) {
            return;
        }
        if ($this->teamId === null) {
            $this->error = 'Select a team on the dashboard. If none are listed, sync with Sunny first.';

            return;
        }
        if ($this->teamId !== app(SunnyTeam::class)->current()?->id) {
            $this->error = 'The active team changed. Reopen this form from the dashboard before saving.';

            return;
        }
        $this->saving = true;
        try {
            $this->savedId = app(SunnyOutbox::class)->queue($resource, $this->teamId, $payload, $id, $this->photoPath ?? null);
            app(SunnySyncCoordinator::class)->dispatch();
            $path = match ($resource) {
                'items' => 'inventory',
                'checklists' => 'lists',
                default => 'recipes',
            };
            $this->replace('/'.$path.'/'.$this->savedId);
        } catch (Throwable $exception) {
            $this->error = $this->saveFailureMessage($exception);
        } finally {
            $this->saving = false;
        }
    }

    /**
     * Explain to the user why a save couldn't be kept on this phone.
     */
    protected function saveFailureMessage(Throwable $exception): string
    {
        if ($exception instanceof ValidationException) {
            return collect($exception->errors())->flatten()->first() ?? 'Check the form and try again.';
        }

        report($exception);

        return 'Unable to save on this phone. Try again.';
    }
}
