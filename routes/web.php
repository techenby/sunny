<?php

use App\Http\Controllers\AcceptTeamInvitation;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
})->name('home');

Route::view('dashboard', 'dashboard')
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::get('invitations/{invitation}/accept', AcceptTeamInvitation::class)
    ->name('invitation.accept')
    ->middleware('signed');

require __DIR__ . '/settings.php';
require __DIR__ . '/inventory.php';
require __DIR__ . '/recipes.php';
