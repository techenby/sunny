<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::livewire('kiosk', 'pages::kiosk.index')->name('kiosk.index')->withHead(title: 'Kiosk');

Route::livewire('kiosk/pair/{code}', 'pages::kiosk.pair')
    ->middleware(['auth', 'verified'])
    ->name('kiosk.pair')
    ->withHead(title: 'Pair kiosk');

Route::prefix('{current_team}/kiosk')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->name('kiosk')
    ->group(function (): void {
        Route::livewire('configure/preview', 'pages::kiosk.configure.preview')->name('.configure.preview')->withHead(title: 'Kiosk preview');
        Route::livewire('configure/calendar', 'pages::kiosk.configure.calendar')->name('.configure.calendar')->withHead(title: 'Kiosk calendars');
        Route::livewire('configure/settings', 'pages::kiosk.configure.settings')->name('.configure.settings')->withHead(title: 'Kiosk settings');

        Route::livewire('calendar', 'pages::kiosk.calendar')->name('.calendar')->withHead(title: 'Calendar');
        Route::livewire('routines', 'pages::kiosk.routines')->name('.routines')->withHead(title: 'Routines');
        Route::livewire('lists', 'pages::kiosk.lists')->name('.lists')->withHead(title: 'Lists');
        Route::livewire('meal-planning', 'pages::kiosk.meal-planning')->name('.meal-planning')->withHead(title: 'Meal planning');
    });
