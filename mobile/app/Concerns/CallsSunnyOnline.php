<?php

namespace App\Concerns;

use App\Actions\DestroySession;
use App\Http\Integrations\Sunny\EmailVerificationRequired;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Native\Mobile\Facades\Network;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Saloon\Http\Response;
use UnexpectedValueException;

trait CallsSunnyOnline
{
    /**
     * @param  Closure(): void  $call
     * @param  Closure('offline'|'failed'|'locked', Response|null): void  $failed
     */
    protected function callSunny(Closure $call, Closure $failed): bool
    {
        if (Network::status()?->connected === false) {
            $failed('offline', null);

            return false;
        }

        try {
            $call();

            return true;
        } catch (FatalRequestException) {
            $failed('offline', null);
        } catch (EmailVerificationRequired) {
            $this->openEmailVerification();
        } catch (RequestException $exception) {
            if ($exception->getResponse()->status() === 401) {
                rescue(fn () => app(DestroySession::class)->handle(), report: false);
                $this->replace('/');

                return false;
            }

            $failed('failed', $exception->getResponse());
        } catch (AuthenticationException) {
            $this->replace('/');
        } catch (UnexpectedValueException) {
            $failed('failed', null);
        } catch (RuntimeException) {
            $failed('locked', null);
        }

        return false;
    }

    protected static function firstError(?Response $response, ?string $field = null): ?string
    {
        if ($response?->status() !== 422) {
            return null;
        }

        $errors = $response->json('errors') ?? [];
        $messages = $field === null ? collect($errors)->flatten() : collect($errors[$field] ?? []);

        return is_string($messages->first()) ? $messages->first() : null;
    }

    protected static function failureMessage(string $problem, ?Response $response, string $fallback): string
    {
        return match (true) {
            $problem === 'locked' => 'Unable to read your saved login. Unlock your device and try again.',
            $response?->status() === 403 => 'You can’t do that in this team anymore.',
            $response?->status() === 429 => 'Too many attempts. Please wait a minute and try again.',
            default => self::firstError($response) ?? $fallback,
        };
    }
}
