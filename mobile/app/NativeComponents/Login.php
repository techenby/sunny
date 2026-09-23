<?php

namespace App\NativeComponents;

use App\Http\Integrations\Sunny\SunnyAuth;
use Illuminate\Support\Facades\Validator;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class Login extends NativeComponent
{
    public string $email = '';

    public string $password = '';

    public string $code = '';

    public bool $twoFactor = false;

    public bool $useRecoveryCode = false;

    public string $error = '';

    protected string $challenge = '';

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
                $response = $auth->login($this->email, $this->password, 'Sunny Mobile');

                if ($response->json('two_factor') === true) {
                    $challenge = $response->json('challenge');
                    throw_unless(is_string($challenge) && $challenge !== '', RuntimeException::class);
                    $this->challenge = $challenge;
                    $this->twoFactor = true;

                    return;
                }
            }

            $this->startOver();
            $this->replace('/dashboard');
        } catch (RequestException $exception) {
            $status = $exception->getResponse()->status();
            $this->error = match ($status) {
                401 => 'Your credentials were not accepted. Please try again.',
                422 => $this->twoFactor ? 'The code is invalid or expired. Try again or restart login.' : 'Check your email and password and try again.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'Sunny is unavailable right now. Please try again.',
            };
        } catch (FatalRequestException) {
            $this->error = 'Unable to connect to Sunny. Check your connection and try again.';
        } catch (RuntimeException) {
            $this->error = 'Unable to securely complete login. Unlock your device and try again.';
        } finally {
            $this->password = '';
            $this->code = '';
        }
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
