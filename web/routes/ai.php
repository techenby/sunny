<?php

declare(strict_types=1);

use App\Mcp\Servers\SunnyServer;
use Illuminate\Support\Facades\Route;
use Laravel\Mcp\Facades\Mcp;

Route::middleware('throttle:mcp-oauth')->group(fn () => Mcp::oauthRoutes());

Mcp::web('/mcp', SunnyServer::class)
    ->middleware(['auth:api', 'verified', 'throttle:60,1']);
