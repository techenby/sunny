<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\Requests\GetUserRequest;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;

class Home extends NativeComponent
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
                app(SunnyAuth::class)->authenticatedConnector()->send(new GetUserRequest);
                $this->replace('/dashboard');
            } catch (UnauthorizedException) {
                app(SunnyStore::class)->clear();
                app(SunnyTokenStore::class)->forget();
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
