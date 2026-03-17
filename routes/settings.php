<?php

use Illuminate\Support\Facades\Route;
use Laravel\Fortify\Features;

Route::middleware(['auth'])->group(function () {
    Route::redirect('settings', 'settings/profile');

    Route::livewire('settings/profile', 'pages::settings.profile')->name('profile.edit');
    Route::livewire('settings/team', 'pages::settings.team')->name('team.edit');
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
    });
