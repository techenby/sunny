<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::livewire('kiosk', 'pages::kiosk.index')->name('kiosk.index');

Route::livewire('kiosk/pair/{code}', 'pages::kiosk.pair')
    ->middleware(['auth', 'verified'])
    ->name('kiosk.pair');

Route::prefix('{current_team}/kiosk')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->name('kiosk')
    ->group(function (): void {
        Route::livewire('configure/preview', 'pages::kiosk.configure.preview')->name('.configure.preview');
        Route::livewire('configure/calendar', 'pages::kiosk.configure.calendar')->name('.configure.calendar');
        Route::livewire('configure/settings', 'pages::kiosk.configure.settings')->name('.configure.settings');

        Route::livewire('calendar', 'pages::kiosk.calendar')->name('.calendar');
        Route::livewire('routines', 'pages::kiosk.routines')->name('.routines');
        Route::livewire('chore-chart', 'pages::kiosk.chore-chart')->name('.chore-chart');
        Route::livewire('lists', 'pages::kiosk.lists')->name('.lists');
        Route::livewire('meal-planning', 'pages::kiosk.meal-planning')->name('.meal-planning');
    });
