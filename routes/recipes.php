<?php

use Illuminate\Support\Facades\Route;

Route::livewire('recipes/shared/{shareToken}', 'pages::recipes.shared')->name('recipes.shared');

Route::middleware(['auth', 'verified'])->prefix('recipes')->name('recipes')->group(function () {
    Route::livewire('/', 'pages::recipes.index')->name('.index')->middleware('can:viewAny,App\Models\Recipe');
    Route::livewire('create', 'pages::recipes.create')->name('.create')->middleware('can:create,App\Models\Recipe');
    Route::livewire('{recipe}', 'pages::recipes.show')->name('.show')->middleware('can:view,recipe');
    Route::livewire('{recipe}/edit', 'pages::recipes.edit')->name('.edit')->middleware('can:update,recipe');
});
