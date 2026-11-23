<?php

namespace App\Concerns;

use App\Actions\ShareRecipe;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Models\Team;
use App\Support\Offline;
use App\Support\Toast;
use Illuminate\Auth\AuthenticationException;
use JsonException;
use Native\Mobile\Facades\Network;
use Native\Mobile\Facades\Share;
use NativePHP\Clipboard\Facades\Clipboard;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

trait SharesRecipe
{
    public bool $showShare = false;

    public ?string $shareProblem = null;

    public string $shareError = '';

    public bool $shareTurningOff = false;

    public bool $linkCopied = false;

    public function shareUrl(): ?string
    {
        return $this->recipe['share_url'] ?? null;
    }

    public function openShare(): void
    {
        $this->resetShareProblem();
        $this->linkCopied = false;
        $this->showShare = true;
    }

    public function closeShare(): void
    {
        $this->showShare = false;
        $this->resetShareProblem();
    }

    public function turnOnLink(): void
    {
        $this->changeLink(true);
    }

    public function turnOffLink(): void
    {
        $this->changeLink(false);
    }

    public function retryShare(): void
    {
        $this->changeLink(! $this->shareTurningOff);
    }

    public function copyLink(): void
    {
        $url = $this->shareUrl();

        if ($url === null) {
            return;
        }

        $this->linkCopied = Clipboard::writeText($url);

        if (! $this->linkCopied) {
            Toast::error('Unable to copy the link. Please try again.');
        }
    }

    public function sendLink(): void
    {
        $url = $this->shareUrl();

        if ($url !== null) {
            Share::url($this->recipe['name'], '', $url);
        }
    }

    /**
     * @return array{title: string, message: string}
     */
    public function shareNote(): array
    {
        $afterwards = $this->shareTurningOff ? 'The link still works for now.' : 'The recipe is still private, and no link has been made.';

        return match ($this->shareProblem) {
            'offline' => ['title' => Offline::TITLE, 'message' => Offline::ADVICE.' '.$afterwards],
            'unsent' => ['title' => 'Still sending this recipe', 'message' => 'It needs to reach the server before it can have a link. '.$afterwards],
            default => ['title' => $this->shareTurningOff ? 'Couldn’t turn off the link' : 'Couldn’t turn on the link', 'message' => $this->shareError.' '.$afterwards],
        };
    }

    protected function changeLink(bool $shared): void
    {
        $recipe = $this->recipe;

        if ($recipe === null) {
            return;
        }

        $this->resetShareProblem();
        $this->shareTurningOff = ! $shared;
        $this->linkCopied = false;

        if ($recipe['id'] < 0) {
            $this->shareProblem = 'unsent';
            app(SunnySyncCoordinator::class)->dispatch();

            return;
        }

        $team = Team::query()->where('server', SunnyStore::server())->find($recipe['team_id']);

        if (! $team?->slug) {
            $this->shareFailed(SunnySyncCoordinator::REFRESH_ADVICE);

            return;
        }

        if (Network::status()?->connected === false) {
            $this->shareProblem = 'offline';

            return;
        }

        try {
            $record = app(ShareRecipe::class)->handle($team, $recipe['id'], $shared);
        } catch (FatalRequestException) {
            $this->shareProblem = 'offline';

            return;
        } catch (RequestException $exception) {
            $this->shareFailed(match ($exception->getResponse()->status()) {
                403 => 'You no longer have permission to share this recipe.',
                404 => $recipe['name'].' is no longer on the server.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'The server couldn’t change the link right now.',
            });

            return;
        } catch (AuthenticationException|RuntimeException) {
            $this->shareFailed('Unable to read your saved login. Unlock your device and try again.');

            return;
        } catch (JsonException) {
            $record = null;
        }

        if ($record === null) {
            $this->shareFailed('The server didn’t confirm the change.');

            return;
        }

        $this->shareTurningOff = false;
        $this->refreshLocalSyncedData();
    }

    protected function shareFailed(string $message): void
    {
        $this->shareProblem = 'failed';
        $this->shareError = $message;
    }

    protected function resetShareProblem(): void
    {
        $this->shareProblem = null;
        $this->shareError = '';
        $this->shareTurningOff = false;
    }
}
