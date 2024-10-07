<?php

use App\Livewire\Pages\Inventory\Bins;
use App\Livewire\Pages\Inventory\Locations;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::view('/', 'welcome');

Route::middleware(['auth', 'verified'])->group(function () {
    Route::view('dashboard', 'dashboard')->name('dashboard');
    Route::view('profile', 'profile')->name('profile');

    Route::get('inventory/locations', Locations::class)->name('inventory.locations');
    Route::get('inventory/bins', Bins::class)->name('inventory.bins');

    Volt::route('/users', 'pages.users')->name('users');
});

require __DIR__ . '/auth.php';
