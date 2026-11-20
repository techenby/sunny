<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\KioskDevice;
use App\Support\KioskTeam;
use Closure;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureEmailIsVerifiedUnlessPairedKiosk
{
    public function __construct(private EnsureEmailIsVerified $verified) {}

    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (KioskTeam::device() instanceof KioskDevice) {
            return $next($request);
        }

        return $this->verified->handle($request, $next);
    }
}
