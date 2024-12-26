<?php

use App\Actions\CurrentPayPeriod;
use App\Livewire\Pages\Collections\Lego;
use App\Livewire\Pages\Cookbook\CreateRecipe;
use App\Livewire\Pages\Cookbook\EditRecipe;
use App\Livewire\Pages\Cookbook\Recipes;
use App\Livewire\Pages\Cookbook\ShowRecipe;
use App\Livewire\Pages\Inventory\Bins;
use App\Livewire\Pages\Inventory\Locations;
use App\Livewire\Pages\Inventory\Things;
use App\Livewire\Pages\LogPose\Tiles;
use App\Livewire\Pages\Users;
use Illuminate\Support\Facades\Route;

Route::view('/', 'welcome');

Route::get('log-pose', function () {
    if (! app()->environment('local')) {
        abort_if(request()->query('token') !== config('dashboard.token', false), 404);
    }

    return view('log-pose');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('dashboard', function () {
        $payPeriod = (new CurrentPayPeriod)();

        return view('dashboard', ['payPeriod' => $payPeriod]);
    })->name('dashboard');
    Route::view('profile', 'profile')->name('profile');

    Route::get('inventory/locations', Locations::class)->name('inventory.locations');
    Route::get('inventory/bins', Bins::class)->name('inventory.bins');
    Route::get('inventory/things', Things::class)->name('inventory.things');

    Route::get('log-pose/tiles', Tiles::class)->name('log-pose.tiles');

    Route::get('cookbook/recipes', Recipes::class)->name('cookbook.recipes');
    Route::get('cookbook/recipes/create', CreateRecipe::class)->name('cookbook.recipes.create');
    Route::get('cookbook/recipes/{recipe}', ShowRecipe::class)->name('cookbook.recipes.show');
    Route::get('cookbook/recipes/{recipe}/edit', EditRecipe::class)->name('cookbook.recipes.edit');

    Route::get('collections/lego', Lego::class)->name('collections.lego');

    Route::get('users', Users::class)->name('users');
});

require __DIR__ . '/auth.php';
