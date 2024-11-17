<?php

use App\Http\Integrations\OpenWeather\OpenWeather;
use App\Http\Integrations\OpenWeather\Requests\OneCall;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Spatie\Dashboard\Models\Tile;

Artisan::command('app:clear-status', function () {
    $twoHoursAgo = now()->subHours(2);

    User::whereNotNull('status')
        ->where('updated_at', '<=', $twoHoursAgo)
        ->update(['status' => null]);
})->purpose('Clear stale statuses')->everyMinute();

Artisan::command('app:fetch-weather', function () {
    $data = (new OpenWeather)->send(new OneCall)->json();

    Tile::updateOrCreate(
        ['name' => 'weather'],
        ['data' => $data],
    );
})->purpose('Fetch weather data')->everyTwoMinutes();

Schedule::command('app:fetch-calendar-events')->everyFiveMinutes();
