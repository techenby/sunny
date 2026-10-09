<?php

use App\Http\Controllers\AppLinkAssociationController;
use App\Http\Controllers\CostsController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome')->name('home');
Route::view('privacy', 'legal.privacy')->name('privacy');
Route::view('terms', 'legal.terms')->name('terms');
Route::get('costs', CostsController::class)->name('costs');

Route::get('.well-known/apple-app-site-association', [AppLinkAssociationController::class, 'apple'])->name('app-links.apple');
Route::get('.well-known/assetlinks.json', [AppLinkAssociationController::class, 'android'])->name('app-links.android');

Route::prefix('{current_team}')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->group(function () {
        Route::livewire('dashboard', 'pages::dashboard')->name('dashboard');
    });

Route::middleware(['auth'])->group(function () {
    Route::livewire('invitations/{invitation}/accept', 'pages::teams.accept-invitation')->name('invitations.accept');
});

require __DIR__ . '/admin.php';
require __DIR__ . '/inventory.php';
require __DIR__ . '/kiosk.php';
require __DIR__ . '/lists.php';
require __DIR__ . '/recipes.php';
require __DIR__ . '/routines.php';
require __DIR__ . '/settings.php';
