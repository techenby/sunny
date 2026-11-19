<?php

namespace App\NativeComponents;

use App\Actions\DestroySession;
use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Onboarding;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
use Native\Mobile\Attributes\Lazy;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;

#[Lazy]
class Home extends Component
{
    public string $error = '';

    public function mount(): void
    {
        $this->restoreSession();
    }

    public function restoreSession(): void
    {
        $this->error = '';

        try {
            try {
                $connector = app(SunnyAuth::class)->authenticatedConnector();

                if (app(SunnyStore::class)->lastSyncedAt() && app(SunnyStore::class)->rememberedAccount() !== null) {
                    $this->replace($this->signedInDestination());

                    return;
                }

                $profile = $connector->send(new GetUserRequest)->json('data');
                $userId = $profile['id'] ?? null;
                if (is_int($userId)) {
                    app(SunnyStore::class)->rememberAccount($userId);
                    app(SunnyStore::class)->rememberProfile($profile);
                }
                $this->replace($this->signedInDestination());
            } catch (UnauthorizedException) {
                app(DestroySession::class)->handle();
            }
        } catch (AuthenticationException) {
            app(SunnyStore::class)->clear();
        } catch (RequestException|FatalRequestException) {
            if (app(SunnyStore::class)->lastSyncedAt()) {
                $this->replace('/dashboard');

                return;
            }

            $this->error = 'Unable to check your session. Check your connection and try again.';
        } catch (RuntimeException) {
            $this->error = 'Unable to read your saved login. Unlock your device and try again.';
        }
    }

    private function signedInDestination(): string
    {
        if ((app(SunnyStore::class)->profile()['verified'] ?? null) === false) {
            return '/verify-email';
        }

        return Onboarding::needsSetUp() ? '/setup' : '/dashboard';
    }

    public function render(): View
    {
        return view('native.home');
    }
}
