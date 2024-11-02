<?php

use App\Livewire\Pages\Inventory\Bins;
use App\Livewire\Pages\Inventory\Locations;
use App\Livewire\Pages\Inventory\Things;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::view('log-pose', 'log-pose');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');

    Route::get('inventory/locations', Locations::class)->name('inventory.locations');
    Route::get('inventory/bins', Bins::class)->name('inventory.bins');
    Route::get('inventory/things', Things::class)->name('inventory.things');

    Volt::route('/users', 'pages.users')->name('users');
});

require __DIR__ . '/auth.php';
