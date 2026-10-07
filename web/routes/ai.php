<?php

declare(strict_types=1);

use App\Mcp\Servers\SunnyServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware('throttle:30,1')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp', SunnyServer::class)
    ->middleware(['auth:api', 'throttle:60,1']);
