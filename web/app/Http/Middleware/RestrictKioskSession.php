<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Support\KioskTeam;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictKioskSession
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! KioskTeam::inKioskSession()) {
            return $next($request);
        }

        if ($this->isKioskPath($request)) {
            return $next($request);
        }

        abort(403, 'This kiosk session is restricted to kiosk pages.');
    }

    protected function isKioskPath(Request $request): bool
    {
        $path = '/' . ltrim($request->path(), '/');

        if (str_starts_with($path, '/livewire/') || str_starts_with($path, '/livewire-')) {
            return true;
        }

        if ($path === '/kiosk') {
            return true;
        }

        $segments = explode('/', trim($path, '/'));

        if (count($segments) < 3 || $segments[1] !== 'kiosk' || $segments[2] === 'configure') {
            return false;
        }

        return $segments[0] === KioskTeam::device()?->team->slug;
    }
}
