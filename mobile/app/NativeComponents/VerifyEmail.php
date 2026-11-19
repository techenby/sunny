<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyAccount;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Support\Toast;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Facades\Dialog;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class VerifyEmail extends Component
{
    public string $email = '';

    public bool $sent = false;

    public bool $checking = false;

    public string $error = '';

    public function mount(): void
    {
        $this->email = app(SunnyStore::class)->profile()['email'] ?? '';
        $this->checkInBackground();
    }

    public function onResume(): void
    {
        $this->email = app(SunnyStore::class)->profile()['email'] ?? $this->email;
        $this->checkInBackground();
    }

    public function send(): void
    {
        $this->error = '';

        if (Network::status()?->connected === false) {
            $this->error = 'Sending a verification email needs a connection.';

            return;
        }

        try {
            $sent = app(SunnyAccount::class)->sendVerificationEmail();
        } catch (FatalRequestException) {
            $this->error = 'Sending a verification email needs a connection.';

            return;
        } catch (RequestException $exception) {
            $this->error = $exception->getResponse()->status() === 429
                ? 'Too many attempts. Please wait a minute and try again.'
                : 'Sunny couldn’t send the email right now.';

            return;
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RuntimeException) {
            $this->error = 'Unable to read your saved login. Unlock your device and try again.';

            return;
        }

        if (! $sent) {
            $this->continueToSunny();

            return;
        }

        $this->sent = true;
        Toast::success('Verification email sent to '.$this->email);
    }

    public function check(): void
    {
        $this->error = '';
        $this->checking = true;

        try {
            $profile = app(SunnyAccount::class)->refresh();
        } catch (FatalRequestException) {
            $this->error = 'Checking needs a connection.';

            return;
        } catch (RequestException) {
            $this->error = 'Sunny couldn’t check right now. Try again in a moment.';

            return;
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RuntimeException) {
            $this->error = 'Unable to read your saved login. Unlock your device and try again.';

            return;
        } finally {
            $this->checking = false;
        }

        if ($profile !== null && $profile['verified']) {
            $this->continueToSunny();

            return;
        }

        $this->email = $profile['email'] ?? $this->email;
        $this->error = 'Your email isn’t verified yet. Open the link we sent to '.$this->email.', then try again.';
    }

    public function confirmLogOut(): void
    {
        Dialog::alert('Log out?', 'Recipes, inventory, and routines downloaded to this device will be removed, including changes that haven’t been sent yet.', [
            ['label' => 'Cancel', 'style' => 'cancel'],
            ['label' => 'Log out', 'style' => 'destructive'],
        ])->id('verify-log-out')->show();
    }

    #[On(ButtonPressed::class)]
    public function onAlertButtonPressed(string $label, ?string $id = null): void
    {
        if ($id !== 'verify-log-out' || $label !== 'Log out') {
            return;
        }

        try {
            app(SunnyAuth::class)->logout();
        } catch (RequestException|FatalRequestException) {
        } catch (RuntimeException) {
            Toast::error('Unable to clear your saved login. Unlock your device and try again.');

            return;
        }

        $this->replace('/');
    }

    public function render(): View
    {
        return view('native.verify-email');
    }

    private function checkInBackground(): void
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
            ->finished(function (?array $profile): void {
                if ($profile !== null && $profile['verified']) {
                    $this->continueToSunny();
                }
            })
            ->failed(fn () => null);
    }

    private function continueToSunny(): void
    {
        SunnySyncCoordinator::forgetDispatch();
        Toast::success('Email verified');
        $this->replace('/');
    }
}
