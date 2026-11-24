<?php

namespace App\Concerns;

use App\Actions\CopyRecipeToTeam;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Team;
use App\Support\Offline;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Support\Str;
use JsonException;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

trait CopiesRecipeToTeam
{
    public bool $showCopy = false;

    public ?int $copyTeamId = null;

    public ?string $copyProblem = null;

    public string $copyError = '';

    /** @var array<int, string> */
    public array $copyUuids = [];

    public ?string $copiedTo = null;

    /**
     * @return array<int, string>
     */
    public function copyTeams(): array
    {
        $teamId = $this->recipe['team_id'] ?? null;

        return collect(app(SunnyTeam::class)->choices())->except($teamId)->all();
    }

    public function openCopy(): void
    {
        $teams = $this->copyTeams();

        if ($teams === []) {
            return;
        }

        if (! isset($teams[$this->copyTeamId])) {
            $this->copyTeamId = array_key_first($teams);
        }

        $this->copyProblem = null;
        $this->copyError = '';
        $this->showCopy = true;
    }

    public function closeCopy(): void
    {
        $this->showCopy = false;
        $this->copyProblem = null;
        $this->copyError = '';
    }

    public function pickCopyTeam(int $id): void
    {
        if (isset($this->copyTeams()[$id])) {
            $this->copyTeamId = $id;
            $this->copyProblem = null;
            $this->copyError = '';
        }
    }

    public function copyToTeam(): void
    {
        $recipe = $this->recipe;
        $teams = $this->copyTeams();

        if ($recipe === null || ! isset($teams[$this->copyTeamId])) {
            return;
        }

        $this->copyProblem = null;
        $this->copyError = '';

        if ($recipe['id'] < 0 || $this->queuedChange !== null) {
            $this->copyProblem = 'unsent';
            app(SunnySyncCoordinator::class)->dispatch();

            return;
        }

        $from = Team::query()->where('server', SunnyStore::server())->find($recipe['team_id']);
        $to = Team::query()->where('server', SunnyStore::server())->find($this->copyTeamId);

        if (! $from?->slug || $to === null) {
            $this->copyFailed(SunnySyncCoordinator::REFRESH_ADVICE);

            return;
        }

        if (Network::status()?->connected === false) {
            $this->copyProblem = 'offline';

            return;
        }

        $this->copyUuids[$to->id] ??= (string) Str::uuid();

        try {
            $copy = app(CopyRecipeToTeam::class)->handle($from, $recipe['id'], $to, $this->copyUuids[$to->id]);
        } catch (FatalRequestException) {
            $this->copyProblem = 'offline';

            return;
        } catch (RequestException $exception) {
            $this->copyFailed(match ($exception->getResponse()->status()) {
                403 => 'You no longer have permission to copy this recipe.',
                404 => $recipe['name'].' is no longer on the server.',
                422 => $teams[$to->id].' isn’t one of your teams anymore.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'The server couldn’t copy it right now.',
            });
            app(SunnySyncCoordinator::class)->dispatch();

            return;
        } catch (AuthenticationException|RuntimeException) {
            $this->copyFailed('Unable to read your saved login. Unlock your device and try again.');

            return;
        } catch (JsonException) {
            $copy = null;
        }

        if ($copy === null) {
            $this->copyFailed('The server didn’t confirm the copy.');
            app(SunnySyncCoordinator::class)->dispatch();

            return;
        }

        unset($this->copyUuids[$to->id]);
        $this->showCopy = false;
        $this->copiedTo = $teams[$to->id];
    }

    /**
     * @return array{title: string, message: string}
     */
    public function copyNote(): array
    {
        if ($this->copyProblem === 'offline') {
            return ['title' => Offline::TITLE, 'message' => Offline::ADVICE];
        }

        [$title, $message] = match ($this->copyProblem) {
            'unsent' => ['Still sending your changes', 'The server copies what it has, so your latest changes need to reach it first.'],
            default => ['Couldn’t copy', $this->copyError],
        };

        return ['title' => $title, 'message' => $message.' Nothing’s been copied yet.'];
    }

    protected function copyFailed(string $message): void
    {
        $this->copyProblem = 'failed';
        $this->copyError = $message;
    }
}
