<?php

namespace App\NativeComponents;

use App\Actions\DestroySession;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Team;
use App\Support\Toast;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
use Native\Mobile\Facades\Browser;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class DeleteAccount extends Component
{
    public string $password = '';

    public string $error = '';

    /** @var array<string, string> */
    public array $blockingTeams = [];

    public function submit(): void
    {
        $this->error = '';
        $this->blockingTeams = [];

        if ($this->password === '') {
            $this->error = 'Enter your password to delete your account.';

            return;
        }

        try {
            app(SunnyAuth::class)->deleteAccount($this->password);
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RequestException $exception) {
            $response = $exception->getResponse();

            if ($response->status() === 401) {
                rescue(fn () => app(DestroySession::class)->handle(), report: false);
                $this->replace('/');

                return;
            }

            $this->error = match ($response->status()) {
                422 => collect($response->json('errors') ?? [])->flatten()->first() ?? 'The server couldn’t delete your account.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'The server is unavailable right now. Your account was not deleted.',
            };

            if ($response->status() === 422) {
                $this->blockingTeams = collect($response->json('blocking_teams') ?? [])
                    ->filter(fn (mixed $team): bool => is_array($team) && is_string($team['slug'] ?? null) && is_string($team['name'] ?? null))
                    ->mapWithKeys(fn (array $team): array => [$team['slug'] => $team['name']])
                    ->all();
            }

            return;
        } catch (FatalRequestException) {
            $this->error = 'Unable to connect to the server. Your account was not deleted.';

            return;
        } catch (RuntimeException) {
            $this->error = 'Unable to read your saved login. Unlock your device and try again.';

            return;
        } finally {
            $this->password = '';
        }

        $this->replace('/');
    }

    public function openTeamSettings(string $slug): void
    {
        if (! array_key_exists($slug, $this->blockingTeams)) {
            return;
        }

        $team = Team::query()->where('server', SunnyStore::server())->where('slug', $slug)->first();

        if ($team !== null) {
            app(SunnyTeam::class)->select($team->id);
            $this->navigate('/preferences/team');

            return;
        }

        if (! Browser::inApp(app(SunnyConnector::class)->siteUrl().'/settings/teams/'.rawurlencode($slug))) {
            Toast::error('Unable to open the website. Please try again.');
        }
    }

    public function render(): View
    {
        return view('native.delete-account');
    }
}
