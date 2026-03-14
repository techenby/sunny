<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->group(function () {
    Route::livewire('inventory', 'pages::inventory.index')->name('inventory.index');
});
