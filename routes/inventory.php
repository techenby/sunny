<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('inventory', 'pages::inventory.index')->name('inventory.index');
    Route::livewire('inventory/containers', 'pages::inventory.containers')->name('inventory.containers');
    Route::livewire('inventory/items', 'pages::inventory.items')->name('inventory.items');
});
