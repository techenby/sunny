<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Symfony\Component\HttpFoundation\Response;

class TrackLastActivity
{
    public const THROTTLE_MINUTES = 5;

    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $user = $request->user();

        if ($user === null || ($request->hasSession() && $request->session()->has('kiosk_device_id'))) {
            return $response;
        }

        $lastActiveAt = $user->getAttributes()['last_active_at'] ?? null;

        if ($lastActiveAt === null || Date::parse($lastActiveAt)->lt(now()->subMinutes(self::THROTTLE_MINUTES))) {
            $user->forceFill(['last_active_at' => now()])->save();
        }

        return $response;
    }
}
