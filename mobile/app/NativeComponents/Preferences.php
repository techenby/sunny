<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyAccount;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyConnector;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Support\CrashReports;
use App\Support\Toast;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\Computed;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class Preferences extends Component
{
    /** @var array{name: string, email: string, verified: bool}|null */
    public ?array $profile = null;

    public ?int $activeTeamId = null;

    public bool $canShareCrashReports = false;

    public bool $shareCrashReports = false;

    public function mount(): void
    {
        $this->activeTeamId = app(SunnyTeam::class)->current()?->id;
        $this->canShareCrashReports = app(CrashReports::class)->available();
        $this->shareCrashReports = app(CrashReports::class)->sharing();
        $this->profile = app(SunnyStore::class)->profile();

        if ($this->profile === null) {
            $this->refreshProfile();
        }
    }

    public function onResume(): void
    {
        $this->profile = app(SunnyStore::class)->profile() ?? $this->profile;
    }

    public static function initials(string $name): string
    {
        $words = preg_split('/\s+/', trim($name), flags: PREG_SPLIT_NO_EMPTY) ?: [];
        $letters = count($words) > 1 ? [reset($words), end($words)] : array_slice($words, 0, 1);

        return mb_strtoupper(implode('', array_map(fn (string $word): string => mb_substr($word, 0, 1), $letters)));
    }

    public function siteHost(): string
    {
        return (string) parse_url(app(SunnyConnector::class)->siteUrl(), PHP_URL_HOST);
    }

    /**
     * @return array<int, string>
     */
    #[Computed]
    public function teamOptions(): array
    {
        return app(SunnyTeam::class)->choices();
    }

    public function selectTeam(int $id): void
    {
        if (array_key_exists($id, $this->teamOptions)) {
            app(SunnyTeam::class)->select($id);
            $this->activeTeamId = $id;
        }
    }

    public function setCrashReports(bool $share): void
    {
        $crashReports = app(CrashReports::class);

        if (! $crashReports->share($share)) {
            Toast::error('Unable to save your choice. Please try again.');
        }

        $crashReports->markAsked();
        $this->shareCrashReports = $crashReports->sharing();
    }

    public function openSecuritySettings(): void
    {
        $this->openSite('/settings/security');
    }

    public function openPrivacyPolicy(): void
    {
        $this->openSite('/privacy');
    }

    public function openTerms(): void
    {
        $this->openSite('/terms');
    }

    public function confirmLogOut(): void
    {
        Dialog::alert('Log out?', 'Recipes, inventory, and routines downloaded to this device will be removed.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Log out', 'style' => 'destructive'],
        ])->id('log-out')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id === 'log-out' && $label === 'Log out') {
            $this->logOut();
        }
    }

    public function logOut(): void
    {
        try {
            app(SunnyAuth::class)->logout();
        } catch (RequestException|FatalRequestException) {
            Toast::info('Signed out on this device. Sunny could not revoke the remote session.', long: true);
        } catch (RuntimeException) {
            Toast::error('Unable to clear your saved login. Unlock your device and try again.');

            return;
        }

        $this->replace('/');
    }

    private function refreshProfile(): void
    {
        if (Network::status()?->connected === false) {
            return;
        }

        try {
            $token = app(SunnyTokenStore::class)->get();
        } catch (RuntimeException) {
            return;
        }

        if ($token === null) {
            return;
        }

        AsyncTask::dispatch(static fn (): ?array => app(SunnyAccount::class)->refresh($token))
            ->finished(fn (?array $profile) => $this->profile = $profile ?? $this->profile)
            ->failed(fn () => null);
    }

    private function openSite(string $path): void
    {
        if (! Browser::inApp(app(SunnyConnector::class)->siteUrl().$path)) {
            Toast::error('Unable to open the Sunny website. Please try again.');
        }
    }

    public function render(): View
    {
        return view('native.preferences');
    }
}
