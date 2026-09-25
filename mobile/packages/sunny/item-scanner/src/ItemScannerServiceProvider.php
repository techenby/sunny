<?php

namespace Sunny\ItemScanner;

use Illuminate\Support\ServiceProvider;

class ItemScannerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ItemScanner::class);
    }
}
