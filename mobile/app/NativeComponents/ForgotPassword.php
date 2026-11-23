<?php

namespace App\NativeComponents;

use App\Concerns\NeedsConnection;
use App\Http\Integrations\Sunny\SunnyAuth;
use App\Support\Toast;
use Illuminate\View\View;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Facades\Network;

class ForgotPassword extends Component
{
    use NeedsConnection;

    public string $email = '';

    public string $emailError = '';

    public ?string $failure = null;

    public ?int $retryAt = null;

    public bool $sending = false;

    public string $sentTo = '';

    public string $sentMessage = '';

    public function mount(): void
    {
        $this->email = (string) $this->data('email', '');
    }

    public function send(): void
    {
        if ($this->sending || $this->throttled()) {
            return;
        }

        $this->failure = null;
        $this->emailError = trim($this->email) === '' ? 'Enter your email address.' : '';

        if ($this->emailError !== '') {
            return;
        }

        if (Network::status()?->connected === false) {
            $this->failure = 'offline';

            return;
        }

        $this->sending = true;
        $email = trim($this->email);

        $this->async(static fn (): array => app(SunnyAuth::class)->sendPasswordResetLink($email))
            ->finished(fn (array $result) => $this->sent($email, $result['status'] ?? 0, $result['body'] ?? []))
            ->failed(fn () => $this->sent($email, 500, []));
    }

    public function throttled(): bool
    {
        return $this->retryAt !== null && now()->getTimestamp() < $this->retryAt;
    }

    public function backToLogin(): void
    {
        $this->back();
    }

    public function openMail(): void
    {
        if (! Browser::open('message://')) {
            Toast::error('Unable to open Mail.');
        }
    }

    public function render(): View
    {
        return view('native.forgot-password');
    }

    /**
     * @param  array<array-key, mixed>  $body
     */
    private function sent(string $email, int $status, array $body): void
    {
        $this->sending = false;

        if ($status === 202) {
            $this->sentTo = $email;
            $this->sentMessage = is_string($body['message'] ?? null) ? $body['message'] : 'If that email has an account, we’ve sent it a link to reset the password.';

            return;
        }

        if ($status === 422) {
            $this->emailError = collect($body['errors']['email'] ?? [])->filter(fn (mixed $message): bool => is_string($message))->implode(' ');

            if ($this->emailError !== '') {
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
}
