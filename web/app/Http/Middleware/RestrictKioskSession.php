<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\KioskDevice;
use App\Support\KioskTeam;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class RestrictKioskSession
{
    /** @param  Closure(Request): (Response)  $next */
    public function handle(Request $request, Closure $next): Response
    {
        if (! KioskTeam::inKioskSession()) {
            return $next($request);
        }

        $device = KioskTeam::device();

        if (! $device instanceof KioskDevice) {
            $this->signOut($request);

            if ($request->is('kiosk')) {
                return $next($request);
            }

            abort_if($request->hasHeader('X-Livewire'), 401);

            return redirect()->route('kiosk.index');
        }

        if ($this->isKioskPath($request, $device)) {
            return $next($request);
        }

        abort(403, 'This kiosk session is restricted to kiosk pages.');
    }

    protected function isKioskPath(Request $request, KioskDevice $device): bool
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

        return $segments[0] === $device->team->slug;
    }

    protected function signOut(Request $request): void
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();
    }
}
