<?php

use App\Http\Integrations\OpenWeather\OpenWeather;
use App\Http\Integrations\OpenWeather\Requests\OneCall;
use App\Models\Tile;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('app:clear-status', function () {
    $twoHoursAgo = now()->subHours(2);

    User::whereNotNull('status')
        ->where('updated_at', '<=', $twoHoursAgo)
        ->update(['status' => null]);
})->purpose('Clear stale statuses')->everyMinute();

Artisan::command('app:fetch-weather', function () {
    foreach (Tile::where('type', 'weather')->get() as $tile) {
        $tile->data = (new OpenWeather)->send(new OneCall($tile->settings['lat'], $tile->settings['lon']))->json();
        $tile->save();
    }
})->purpose('Fetch weather data')->everyTwoMinutes();

Schedule::command('app:fetch-calendar-events')->everyFiveMinutes();
