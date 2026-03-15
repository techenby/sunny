<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified'])->prefix('inventory')->name('inventory')->group(function () {
    Route::livewire('/', 'pages::inventory.index')->name('.index')->middleware('can:viewAny,App\Models\Item');
    Route::livewire('/{item}', 'pages::inventory.show')->name('.show')->middleware('can:view,item');
});
