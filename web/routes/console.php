<?php

use App\Models\TeamInvitation;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// The board generates whatever date it renders, so this is a warm-up rather
// than a correctness requirement — a missed run costs nothing.
Schedule::command('routines:generate')->dailyAt('00:15')->withoutOverlapping();

// Teams live in different timezones, so this runs hourly and saves each
// team's days as they end there. Missed runs are caught up on the next one.
Schedule::command('meals:save-history')->hourly()->withoutOverlapping();

Schedule::command('sanctum:prune-expired --hours=24')->daily();

Schedule::command('passport:purge --hours=24')->daily();

Schedule::call(function () {
    TeamInvitation::query()
        ->whereNotNull('expires_at')
        ->where('expires_at', '<', now())
        ->delete();
})->daily()->description('Delete expired team invitations');
