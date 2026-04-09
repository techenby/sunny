<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])
    ->prefix('settings')
    ->group(function (): void {
        Route::redirect('/', '/settings/profile');

        Route::livewire('profile', 'pages::settings.profile')->name('profile.edit');
    });

Route::middleware(['auth', 'verified'])
    ->prefix('settings')
    ->group(function (): void {
        Route::livewire('password', 'pages::settings.password')->name('user-password.edit');
        Route::livewire('appearance', 'pages::settings.appearance')->name('appearance.edit');

        Route::livewire('two-factor', 'pages::settings.two-factor')
            ->middleware(
                when(
                    Features::canManageTwoFactorAuthentication()
                    && Features::optionEnabled(Features::twoFactorAuthentication(), 'confirmPassword'),
                    ['password.confirm'],
                    [],
                ),
            )
            ->name('two-factor.show');

        Route::livewire('teams', 'pages::teams.index')->name('teams.index');

        Route::middleware(EnsureTeamMembership::class)->group(function () {
            Route::livewire('teams/{team}', 'pages::teams.edit')->name('teams.edit');
        });
    });
