<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\KioskTeam;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureKioskEmailIsVerified extends EnsureEmailIsVerified
{
    /** @param Closure(Request): (Response) $next */
    public function handle($request, Closure $next, $redirectToRoute = null): Response
    {
        if (KioskTeam::inKioskSession() && KioskTeam::device() !== null) {
            return $next($request);
        }

        return parent::handle($request, $next, $redirectToRoute);
    }
}
