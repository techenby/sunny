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
                    $this->replace(Onboarding::needsSetUp() ? '/setup' : '/dashboard');

                    return;
                }

                $userId = $connector->send(new GetUserRequest)->json('data.id');
                if (is_int($userId)) {
                    app(SunnyStore::class)->rememberAccount($userId);
                }
                $this->replace(Onboarding::needsSetUp() ? '/setup' : '/dashboard');
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

    public function render(): View
    {
        return view('native.home');
    }
}
