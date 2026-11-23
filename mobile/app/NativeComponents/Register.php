<?php

namespace App\NativeComponents;

use App\Concerns\NeedsConnection;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Http\Integrations\Sunny\SunnyConnector;
use App\Support\Toast;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Network;
use RuntimeException;

class Register extends Component
{
    use NeedsConnection;

    public string $name = '';

    public string $email = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    /** @var array<string, string> */
    public array $fieldErrors = [];

    public bool $emailTaken = false;

    public ?string $failure = null;

    public ?int $retryAt = null;

    public bool $saving = false;

    public string $error = '';

    public function submit(): void
    {
        if ($this->saving || $this->throttled()) {
            return;
        }

        $this->failure = null;
        $this->error = '';
        $this->emailTaken = false;
        $this->fieldErrors = array_filter([
            'name' => trim($this->name) === '' ? 'Enter your name.' : null,
            'email' => trim($this->email) === '' ? 'Enter your email address.' : null,
            'password' => $this->password === '' ? 'Choose a password.' : null,
            'password_confirmation' => $this->password !== '' && $this->passwordConfirmation !== $this->password ? 'The passwords don’t match.' : null,
        ]);

        if ($this->fieldErrors !== []) {
            return;
        }

        if (Network::status()?->connected === false) {
            $this->failure = 'offline';

            return;
        }

        $this->saving = true;
        $name = trim($this->name);
        $email = trim($this->email);
        $password = $this->password;
        $passwordConfirmation = $this->passwordConfirmation;

        $this->async(static fn (): array => app(SunnyAuth::class)->register($name, $email, $password, $passwordConfirmation, 'Sunny Mobile'))
            ->finished(fn (array $result) => $this->registered($result['status'] ?? 0, $result['body'] ?? []))
            ->failed(fn () => $this->registered(500, []));
    }

    public function throttled(): bool
    {
        return $this->retryAt !== null && now()->getTimestamp() < $this->retryAt;
    }

    public function showLogin(): void
    {
        $email = trim($this->email);

        if ($this->data('from') === 'login') {
            if ($email !== '') {
                Cache::put(Login::EMAIL_HANDOFF, $email, now()->addMinutes(5));
            }

            $this->back();

            return;
        }

        $this->navigate('/login', ['from' => 'register', 'email' => $email]);
    }

    public function openTerms(): void
    {
        $this->openSite('/terms');
    }

    public function openPrivacyPolicy(): void
    {
        $this->openSite('/privacy');
    }

    public function render(): View
    {
        return view('native.register');
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function registered(int $status, array $body): void
    {
        $this->saving = false;

        if ($status === 201) {
            try {
                app(SunnyAuth::class)->signIn($body);
            } catch (RuntimeException) {
                $this->error = 'Your account was created, but Sunny couldn’t sign you in. Log in to continue.';

                return;
            }

            $this->password = '';
            $this->passwordConfirmation = '';
            $this->replace('/');

            return;
        }

        if ($status === 422) {
            $this->fieldErrors = collect($body['errors'] ?? [])
                ->only(['name', 'email', 'password'])
                ->map(fn (mixed $messages): string => collect($messages)->filter(fn (mixed $message): bool => is_string($message))->implode(' '))
                ->filter()
                ->all();

            if (str_contains($this->fieldErrors['email'] ?? '', 'already been taken')) {
                $this->emailTaken = true;
                $this->fieldErrors['email'] = 'An account already uses this email.';
            }

            if ($this->fieldErrors !== []) {
                return;
            }
        }

        if ($status === 429) {
            $this->retryAt = now()->addMinute()->getTimestamp();
        }

        $this->failure = match ($status) {
            0 => 'offline',
            404 => 'unavailable',
            429 => 'throttled',
            default => 'server',
        };
    }

    private function openSite(string $path): void
    {
        if (! Browser::inApp(app(SunnyConnector::class)->siteUrl().$path)) {
            Toast::error('Unable to open the Sunny website. Please try again.');
        }
    }
}
