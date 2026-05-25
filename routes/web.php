<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::view('dashboard', 'dashboard')->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__ . '/admin.php';
require __DIR__ . '/inventory.php';
require __DIR__ . '/recipes.php';
require __DIR__ . '/settings.php';

Route::prefix('{current_team}/kiosk')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->name('kiosk')
    ->group(function (): void {
        Route::livewire('configure', 'pages::kiosk.configure')->name('.configure');

        Route::livewire('calendar', 'pages::kiosk.calendar')->name('.calendar');
        Route::livewire('routines', 'pages::kiosk.routines')->name('.routines');
        Route::livewire('chore-chart', 'pages::kiosk.chore-chart')->name('.chore-chart');
        Route::livewire('lists', 'pages::kiosk.lists')->name('.lists');
        Route::livewire('meal-planning', 'pages::kiosk.meal-planning')->name('.meal-planning');
        Route::livewire('settings', 'pages::kiosk.settings')->name('.settings');
    });
