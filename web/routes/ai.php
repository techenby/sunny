<?php

declare(strict_types=1);

use App\Mcp\Servers\SunnyServer;
use Laravel\Mcp\Facades\Mcp;

Mcp::web('/mcp', SunnyServer::class)
    ->middleware(['auth:sanctum', 'throttle:60,1']);
