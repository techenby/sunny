<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAccountForms
{
    public const int PER_MINUTE = 6;

    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register.store', 'password.email')) {
            return $next($request);
        }

        $key = 'account-form:' . $request->route()->getName() . '|' . $request->ip();

        if (RateLimiter::tooManyAttempts($key, self::PER_MINUTE)) {
            $seconds = RateLimiter::availableIn($key);

            throw ValidationException::withMessages([
                'email' => [__('Too many attempts. Please try again in :seconds seconds.', ['seconds' => $seconds])],
            ])->status(Response::HTTP_TOO_MANY_REQUESTS);
        }

        RateLimiter::hit($key);

        return $next($request);
    }
}
