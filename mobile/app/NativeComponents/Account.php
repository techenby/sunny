<?php

namespace App\NativeComponents;

use App\Actions\DestroySession;
use App\Http\Integrations\Sunny\SunnyAccount;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Support\Toast;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
use Native\Mobile\AsyncTask;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class Account extends Component
{
    public string $name = '';

    public string $email = '';

    public string $savedEmail = '';

    public bool $verified = true;

    public bool $emailChanged = false;

    public bool $verificationSent = false;

    public ?string $offline = null;

    public string $error = '';

    /** @var array<string, string> */
    public array $fieldErrors = [];

    public bool $saving = false;

    public function mount(): void
    {
        $this->useProfile(app(SunnyStore::class)->profile());
        $this->refreshProfile();
    }

    public function save(): void
    {
        $this->resetProblems();
        $this->name = trim($this->name);
        $this->email = trim($this->email);

        if ($this->name === '') {
            $this->fieldErrors['name'] = 'Enter your name.';
        }

        if (filter_var($this->email, FILTER_VALIDATE_EMAIL) === false) {
            $this->fieldErrors['email'] = 'Enter a valid email address.';
        }

        if ($this->fieldErrors !== []) {
            return;
        }

        if (Network::status()?->connected === false) {
            $this->offline = 'save';

            return;
        }

        $previousEmail = app(SunnyStore::class)->profile()['email'] ?? null;
        $this->saving = true;

        try {
            $profile = app(SunnyAccount::class)->update($this->name, $this->email);
        } catch (FatalRequestException) {
            $this->offline = 'save';

            return;
        } catch (RequestException $exception) {
            $this->failed($exception, 'Sunny couldn’t save your changes right now.');

            return;
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RuntimeException) {
            $this->error = 'Unable to read your saved login. Unlock your device and try again.';

            return;
        } finally {
            $this->saving = false;
        }

        $this->useProfile($profile);

        if ($previousEmail !== null && $previousEmail !== $this->email) {
            $this->emailChanged = true;
            $this->verificationSent = false;
        }

        Toast::success($this->emailChanged && ! $this->verified ? 'Saved. Your new email isn’t verified yet.' : 'Saved');
    }

    public function sendVerification(): void
    {
        $this->resetProblems();

        if (Network::status()?->connected === false) {
            $this->offline = 'verification';

            return;
        }

        try {
            $sent = app(SunnyAccount::class)->sendVerificationEmail();
        } catch (FatalRequestException) {
            $this->offline = 'verification';

            return;
        } catch (RequestException $exception) {
            $this->failed($exception, 'Sunny couldn’t send the email right now.');

            return;
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RuntimeException) {
            $this->error = 'Unable to read your saved login. Unlock your device and try again.';

            return;
        }

        if ($sent) {
            $this->verificationSent = true;
            Toast::success('Verification email sent to '.$this->savedEmail);

            return;
        }

        $this->verified = true;
        Toast::info('Your email is already verified.');
    }

    public function offlineMessage(): string
    {
        return $this->offline === 'verification'
            ? 'Sending a verification email needs a connection. Nothing’s been sent yet.'
            : 'Changes to your account need a connection. Nothing’s been saved yet.';
    }

    public function render(): View
    {
        return view('native.account');
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

        $saved = app(SunnyStore::class)->profile();

        AsyncTask::dispatch(static fn (): ?array => app(SunnyAccount::class)->refresh($token))
            ->finished(function (?array $profile) use ($saved): void {
                if ($profile === null) {
                    return;
                }

                if ($saved === null || ($this->name === $saved['name'] && $this->email === $saved['email'])) {
                    $this->useProfile($profile);
                } else {
                    $this->savedEmail = $profile['email'];
                    $this->verified = $profile['verified'];
                }
            })
            ->failed(fn () => null);
    }

    /** @param  array{name: string, email: string, verified: bool}|null  $profile */
    private function useProfile(?array $profile): void
    {
        if ($profile === null) {
            return;
        }

        $this->name = $profile['name'];
        $this->email = $profile['email'];
        $this->savedEmail = $profile['email'];
        $this->verified = $profile['verified'];
    }

    private function resetProblems(): void
    {
        $this->offline = null;
        $this->error = '';
        $this->fieldErrors = [];
    }

    private function failed(RequestException $exception, string $fallback): void
    {
        $response = $exception->getResponse();

        if ($response->status() === 401) {
            rescue(fn () => app(DestroySession::class)->handle(), report: false);
            $this->replace('/');

            return;
        }

        if ($response->status() === 422) {
            $this->fieldErrors = collect($response->json('errors') ?? [])
                ->only(['name', 'email'])
                ->map(fn (mixed $messages): string => (string) collect($messages)->first())
                ->all();
        }

        if ($this->fieldErrors === []) {
            $this->error = $response->status() === 429 ? 'Too many attempts. Please wait a minute and try again.' : $fallback;
        }
    }
}
