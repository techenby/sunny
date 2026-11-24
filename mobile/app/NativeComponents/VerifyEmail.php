<?php

namespace App\NativeComponents;

use App\Actions\DestroySession;
use App\Concerns\NeedsConnection;
use App\Http\Integrations\Sunny\SunnyAccount;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Support\Offline;
use App\Support\Toast;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\On;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Exceptions\AsyncTaskException;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Dialog;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Exceptions\Request\Statuses\UnauthorizedException;

class VerifyEmail extends Component
{
    use NeedsConnection;

    public string $email = '';

    public bool $sent = false;

    public bool $sending = false;

    public bool $checking = false;

    /** @var 'offline'|'not-verified'|'throttled'|'send-failed'|'check-failed'|'locked'|null */
    public ?string $problem = null;

    public bool $changing = false;

    public string $newEmail = '';

    public string $newEmailError = '';

    public bool $savingEmail = false;

    /** @var 'offline'|'throttled'|'failed'|'locked'|null */
    public ?string $changeProblem = null;

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
        if ($this->sending) {
            return;
        }

        $this->problem = null;

        if (Offline::now()) {
            $this->problem = 'offline';

            return;
        }

        $this->sending = true;

        try {
            $sent = app(SunnyAccount::class)->sendVerificationEmail();
        } catch (FatalRequestException) {
            $this->problem = 'offline';

            return;
        } catch (RequestException $exception) {
            $status = $exception->getResponse()->status();

            if ($status === 401) {
                $this->signedOut();

                return;
            }

            $this->problem = $status === 429 ? 'throttled' : 'send-failed';

            return;
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RuntimeException) {
            $this->problem = 'locked';

            return;
        } finally {
            $this->sending = false;
        }

        if (! $sent) {
            $this->continueToSunny();

            return;
        }

        $this->sent = true;
    }

    public function check(): void
    {
        if ($this->checking) {
            return;
        }

        $this->problem = null;

        if (Offline::now()) {
            $this->problem = 'offline';

            return;
        }

        $this->checking = true;

        try {
            $profile = app(SunnyAccount::class)->refresh();
        } catch (FatalRequestException) {
            $this->problem = 'offline';

            return;
        } catch (RequestException $exception) {
            if ($exception->getResponse()->status() === 401) {
                $this->signedOut();

                return;
            }

            $this->problem = 'check-failed';

            return;
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RuntimeException) {
            $this->problem = 'locked';

            return;
        } finally {
            $this->checking = false;
        }

        if ($profile !== null && $profile['verified']) {
            $this->continueToSunny();

            return;
        }

        $this->email = $profile['email'] ?? $this->email;
        $this->problem = 'not-verified';
    }

    public function openMail(): void
    {
        if (! Browser::open('message://')) {
            Toast::error('Unable to open Mail.');
        }
    }

    public function openChange(): void
    {
        $this->newEmail = $this->email;
        $this->newEmailError = '';
        $this->changeProblem = null;
        $this->changing = true;
    }

    public function closeChange(): void
    {
        $this->changing = false;
    }

    public function saveEmail(): void
    {
        if ($this->savingEmail) {
            return;
        }

        $this->changeProblem = null;
        $this->newEmailError = '';
        $this->newEmail = trim($this->newEmail);

        if (filter_var($this->newEmail, FILTER_VALIDATE_EMAIL) === false) {
            $this->newEmailError = 'Enter a valid email address.';

            return;
        }

        if ($this->newEmail === $this->email) {
            $this->closeChange();

            return;
        }

        if (Offline::now()) {
            $this->changeProblem = 'offline';

            return;
        }

        $this->savingEmail = true;

        try {
            $profile = app(SunnyAccount::class)->update(app(SunnyStore::class)->profile()['name'] ?? '', $this->newEmail);
        } catch (FatalRequestException) {
            $this->changeProblem = 'offline';

            return;
        } catch (RequestException $exception) {
            $this->changeFailed($exception);

            return;
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RuntimeException) {
            $this->changeProblem = 'locked';

            return;
        } finally {
            $this->savingEmail = false;
        }

        $this->email = $profile['email'] ?? $this->newEmail;
        $this->sent = true;
        $this->problem = null;
        $this->changing = false;
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

    private function changeFailed(RequestException $exception): void
    {
        $response = $exception->getResponse();

        if ($response->status() === 401) {
            $this->signedOut();

            return;
        }

        $emailError = collect($response->json('errors.email') ?? [])->first();

        if ($response->status() === 422 && is_string($emailError)) {
            $this->newEmailError = $emailError;

            return;
        }

        $this->changeProblem = $response->status() === 429 ? 'throttled' : 'failed';
    }

    private function checkInBackground(): void
    {
        if (Offline::now()) {
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
            ->failed(function (AsyncTaskException $exception): void {
                if (is_a($exception->originalClass(), UnauthorizedException::class, true)) {
                    $this->signedOut();
                }
            });
    }

    private function signedOut(): void
    {
        rescue(fn () => app(DestroySession::class)->handle(), report: false);
        $this->replace('/');
    }

    private function continueToSunny(): void
    {
        SunnySyncCoordinator::forgetDispatch();
        Toast::success('Email verified');
        $this->replace('/');
    }
}
