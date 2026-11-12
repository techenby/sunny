<?php

namespace App\NativeComponents;

use App\Actions\DestroySession;
use App\Http\Integrations\Sunny\SunnyAuth;
use Illuminate\Auth\AuthenticationException;
use Illuminate\View\View;
use Native\Mobile\Edge\NativeComponent;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;

class DeleteAccount extends NativeComponent
{
    public string $password = '';

    public string $error = '';

    public function submit(): void
    {
        $this->error = '';

        if ($this->password === '') {
            $this->error = 'Enter your password to delete your account.';

            return;
        }

        try {
            app(SunnyAuth::class)->deleteAccount($this->password);
        } catch (AuthenticationException) {
            $this->replace('/');

            return;
        } catch (RequestException $exception) {
            $response = $exception->getResponse();

            if ($response->status() === 401) {
                rescue(fn () => app(DestroySession::class)->handle(), report: false);
                $this->replace('/');

                return;
            }

            $this->error = match ($response->status()) {
                422 => collect($response->json('errors') ?? [])->flatten()->first() ?? 'Sunny couldn’t delete your account.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'Sunny is unavailable right now. Your account was not deleted.',
            };

            return;
        } catch (FatalRequestException) {
            $this->error = 'Unable to connect to Sunny. Your account was not deleted.';

            return;
        } catch (RuntimeException) {
            $this->error = 'Unable to read your saved login. Unlock your device and try again.';

            return;
        } finally {
            $this->password = '';
        }

        $this->replace('/');
    }

    public function render(): View
    {
        return view('native.delete-account');
    }
}
