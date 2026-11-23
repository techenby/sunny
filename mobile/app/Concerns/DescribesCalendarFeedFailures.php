<?php

namespace App\Concerns;

use App\Support\Offline;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use JsonException;
use RuntimeException;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Exceptions\Request\RequestException;
use Throwable;
use UnexpectedValueException;

trait DescribesCalendarFeedFailures
{
    protected function feedFailureMessage(Throwable $exception, string $action): string
    {
        return match (true) {
            $exception instanceof FatalRequestException => Offline::message(),
            $exception instanceof RequestException => match ($exception->getResponse()->status()) {
                422 => collect($exception->getResponse()->json('errors'))->flatten()->first() ?? 'Check the form and try again.',
                403 => 'You can’t change this team’s calendars.',
                404 => 'This calendar has already been removed.',
                429 => 'Too many attempts. Please wait before trying again.',
                default => 'The server couldn’t '.$action.' right now. Try again in a moment.',
            },
            $exception instanceof JsonException, $exception instanceof ValidationException, $exception instanceof UnexpectedValueException => 'The server sent back something unexpected. Try again in a moment.',
            $exception instanceof AuthenticationException => 'Sign in again to change calendars.',
            $exception instanceof RuntimeException => 'Unable to read your saved login. Unlock your device and try again.',
            default => throw $exception,
        };
    }
}
