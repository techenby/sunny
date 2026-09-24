<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The board generates whatever date it renders, so this is a warm-up rather
// than a correctness requirement — a missed run costs nothing.
Schedule::command('routines:generate')->dailyAt('00:15')->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')->daily();
