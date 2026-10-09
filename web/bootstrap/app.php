<?php

declare(strict_types=1);

use App\Http\Middleware\RestrictKioskSession;
use App\Http\Middleware\SetTeamUrlDefaults;
use App\Http\Middleware\TrackLastActivity;
use App\Models\KioskDevice;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            SetTeamUrlDefaults::class,
            RestrictKioskSession::class,
            TrackLastActivity::class,
        ]);

        $middleware->redirectGuestsTo(fn (Request $request): string => $request->hasCookie(KioskDevice::COOKIE_NAME)
            && $request->is('*/kiosk/*')
            && ! $request->is('*/kiosk/configure/*')
                ? route('kiosk.index')
                : route('login'));

        $middleware->api(append: [
            TrackLastActivity::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
