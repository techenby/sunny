<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Symfony\Component\HttpFoundation\Response;

class ThrottleAccountForms
{
    public function __construct(private ThrottleRequests $throttle) {}

    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->routeIs('register.store', 'password.email')) {
            return $next($request);
        }

        return $this->throttle->handle($request, $next, 'account-forms');
    }
}
