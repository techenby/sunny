<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RestrictKioskSession
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->session()->has('kiosk_device_id')) {
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

        if ($path === '/kiosk' || str_starts_with($path, '/kiosk/')) {
            return true;
        }

        $segments = explode('/', trim($path, '/'));

        return count($segments) >= 2 && $segments[1] === 'kiosk';
    }
}
