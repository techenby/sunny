<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::livewire('recipes/shared/{shareToken}', 'pages::recipes.shared')->name('recipes.shared');

Route::prefix('{current_team}/recipes')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->name('recipes')
    ->group(function (): void {
        Route::livewire('/', 'pages::recipes.index')->name('.index')->middleware('can:viewAny,App\Models\Recipe');
        Route::livewire('create', 'pages::recipes.create')->name('.create')->middleware('can:create,App\Models\Recipe');
        Route::livewire('{recipe}', 'pages::recipes.show')->name('.show')->middleware('can:view,recipe');
        Route::livewire('{recipe}/edit', 'pages::recipes.edit')->name('.edit')->middleware('can:update,recipe');
    });
