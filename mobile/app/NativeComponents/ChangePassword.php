<?php

namespace App\NativeComponents;

use App\Actions\DestroySession;
use App\Concerns\NeedsConnection;
use App\Http\Integrations\Sunny\SunnyAccount;
use App\Layouts\HidesTabBar;
use App\Support\Toast;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class ChangePassword extends Component implements HidesTabBar
{
    use NeedsConnection;

    public string $currentPassword = '';

    public string $password = '';

    public string $passwordConfirmation = '';

    public bool $offline = false;

    public string $error = '';

    /** @var array<string, string> */
    public array $fieldErrors = [];

    public bool $saving = false;

    public function save(): void
    {
        $this->offline = false;
        $this->error = '';
        $this->fieldErrors = array_filter([
            'current_password' => $this->currentPassword === '' ? 'Enter your current password.' : null,
            'password' => $this->password === '' ? 'Enter a new password.' : null,
            'password_confirmation' => $this->password !== '' && $this->passwordConfirmation !== $this->password ? 'The new passwords don’t match.' : null,
        ]);

        if ($this->fieldErrors !== []) {
            return;
        }

        if (Network::status()?->connected === false) {
            $this->offline = true;

            return;
        }

        $this->saving = true;

        try {
            app(SunnyAccount::class)->updatePassword($this->currentPassword, $this->password, $this->passwordConfirmation);
        } catch (FatalRequestException) {
            $this->offline = true;

            return;
        } catch (RequestException $exception) {
            $this->failed($exception);

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

        $this->currentPassword = '';
        $this->password = '';
        $this->passwordConfirmation = '';
        Toast::success('Password changed');
        $this->back();
    }

    public function render(): View
    {
        return view('native.change-password');
    }

    private function failed(RequestException $exception): void
    {
        $response = $exception->getResponse();

        if ($response->status() === 401) {
            rescue(fn () => app(DestroySession::class)->handle(), report: false);
            $this->replace('/');

            return;
        }

        if ($response->status() === 422) {
            $this->fieldErrors = collect($response->json('errors') ?? [])
                ->only(['current_password', 'password', 'password_confirmation'])
                ->map(fn (mixed $messages): string => (string) collect($messages)->first())
                ->all();
        }

        if ($this->fieldErrors === []) {
            $this->error = $response->status() === 429 ? 'Too many attempts. Please wait a minute and try again.' : 'The server couldn’t change your password right now.';
        }
    }
}
