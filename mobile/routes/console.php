<?php

use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sunny:sync')
    ->everyFifteenMinutes()
    ->onAnyNetwork()
    ->when(fn (): bool => app(SunnyStore::class)->isStale());
