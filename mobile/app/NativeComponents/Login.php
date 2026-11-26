<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyAuth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use JsonException;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use UnexpectedValueException;

class Login extends Component
{
    public const EMAIL_HANDOFF = 'login.email';

    public string $email = '';

    public string $password = '';

    public string $code = '';

    public bool $twoFactor = false;

    public bool $useRecoveryCode = false;

    public string $error = '';

    protected string $challenge = '';

    public function mount(): void
    {
        $this->email = (string) $this->data('email', '');
    }

    public function onResume(): void
    {
        $email = Cache::pull(self::EMAIL_HANDOFF);

        if (is_string($email) && $email !== '') {
            $this->email = $email;
        }
    }

    public function submit(): void
    {
        $this->error = '';
        $validator = Validator::make(
            $this->twoFactor ? ['code' => $this->code] : ['email' => $this->email, 'password' => $this->password],
            $this->twoFactor ? ['code' => ['required', 'string']] : ['email' => ['required', 'email'], 'password' => ['required', 'string']],
        );

        if ($validator->fails()) {
            $this->error = $validator->errors()->first();

            return;
        }

        try {
            $auth = app(SunnyAuth::class);

            if ($this->twoFactor) {
                $auth->verifyTwoFactor($this->challenge, $this->code, $this->useRecoveryCode);
            } else {
                $challenge = $auth->login($this->email, $this->password, 'Sunny Mobile');

                if ($challenge !== null) {
                    $this->challenge = $challenge;
                    $this->twoFactor = true;

                    return;
                }
            }

            $this->startOver();
            $this->replaceStack('/');
        } catch (RequestException $exception) {
            $status = $exception->getResponse()->status();
            $this->error = match ($status) {
                301, 302, 303, 307, 308 => 'The server redirected the login request. Check the configured API URL uses HTTPS.',
                401 => 'Your credentials were not accepted. Please try again.',
                422 => $this->twoFactor ? 'The code is invalid or expired. Try again or restart login.' : 'Check your email and password and try again.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'The server is unavailable right now. Please try again.',
            };
        } catch (JsonException|UnexpectedValueException) {
            $this->error = 'The server returned an invalid response. Check the API URL and try again.';
        } catch (FatalRequestException) {
            $this->error = 'Unable to connect to the server. Check your connection and try again.';
        } catch (RuntimeException) {
            $this->error = 'Unable to securely complete login. Unlock your device and try again.';
        } finally {
            $this->password = '';
            $this->code = '';
        }
    }

    public function forgotPassword(): void
    {
        $this->navigate('/forgot-password', ['email' => trim($this->email)]);
    }

    public function showRegister(): void
    {
        if ($this->data('from') === 'register') {
            $this->back();

            return;
        }

        $this->navigate('/register', ['from' => 'login']);
    }

    public function toggleRecoveryCode(): void
    {
        $this->useRecoveryCode = ! $this->useRecoveryCode;
        $this->code = '';
        $this->error = '';
    }

    public function startOver(): void
    {
        $this->challenge = '';
        $this->password = '';
        $this->code = '';
        $this->twoFactor = false;
        $this->useRecoveryCode = false;
        $this->error = '';
    }

    public function render(): View
    {
        return view('native.login');
    }
}
