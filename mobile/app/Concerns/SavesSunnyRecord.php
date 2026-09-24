<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Team;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Native\Mobile\Attributes\Computed;
use Saloon\Exceptions\Request\RequestException;
use Throwable;

trait SavesSunnyRecord
{
    public string $teamName = '';

    public bool $saving = false;

    public ?int $savedId = null;

    public ?int $recordTeamId = null;

    #[Computed]
    public function teamChoices(): array
    {
        return Team::query()->where('server', SunnyStore::server())->orderBy('name')->get()
            ->mapWithKeys(fn (Team $team): array => [$team->id => $team->name.' (#'.$team->id.')'])->all();
    }

    #[Computed]
    public function teamOptions(): array
    {
        return ['Choose a team', ...array_values($this->teamChoices)];
    }

    #[Computed]
    public function teamId(): ?int
    {
        if ($this->recordTeamId !== null) {
            return $this->recordTeamId;
        }
        $id = array_search($this->teamName, $this->teamChoices, true);

        return $id === false ? null : $id;
    }

    protected function initializeTeam(?int $id = null): void
    {
        $this->recordTeamId = $id;
        $this->teamName = $id !== null ? ($this->teamChoices[$id] ?? '')
            : (count($this->teamChoices) === 1 ? array_values($this->teamChoices)[0] : 'Choose a team');
    }

    protected function saveRecord(string $resource, array $payload, ?int $id = null): void
    {
        if ($this->saving || $this->savedId !== null) {
            return;
        }
        if ($this->teamId === null) {
            $this->error = 'Choose a team. If none are listed, sync with Sunny first.';

            return;
        }
        $this->saving = true;
        try {
            $record = app(SunnyWrites::class)->save($resource, $this->teamId, $payload, $id, $this->photoPath);
            $this->savedId = $record['id'];
            app(SunnyStore::class)->saveRecord($resource, $record);
            $path = $resource === 'items' ? 'inventory' : 'recipes';
            $this->replace('/'.$path.'/'.$this->savedId);
        } catch (ValidationException $exception) {
            $this->error = collect($exception->errors())->flatten()->first() ?? 'Check the form and try again.';
        } catch (AuthenticationException) {
            $this->error = 'Your session expired. Log in again before saving.';
        } catch (RequestException $exception) {
            $response = $exception->getResponse();
            $this->error = match ($response->status()) {
                401 => 'Your session expired. Log in again before saving.',
                403 => 'You no longer have permission to save to this team.',
                404 => 'This record or team is no longer available. Sync with Sunny.',
                422 => collect($response->json('errors') ?? [])->flatten()->first() ?? 'Check the form and try again.',
                default => 'Unable to confirm the save. Sync with Sunny before retrying to avoid duplicates.',
            };
        } catch (Throwable $exception) {
            report($exception);
            $this->error = $this->savedId !== null
                ? 'Saved on Sunny, but the local copy could not be updated. Go back and sync to see it.'
                : 'Unable to confirm the save. Check your connection and sync before retrying to avoid duplicates.';
        } finally {
            $this->saving = false;
        }
    }
}
