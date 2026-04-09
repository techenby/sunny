<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}/inventory')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->name('inventory')
    ->group(function (): void {
        Route::livewire('/', 'pages::inventory.index')->name('.index')->middleware('can:viewAny,App\Models\Item');
        Route::livewire('{item}', 'pages::inventory.show')->name('.show')->middleware('can:view,item')->withTrashed();
    });
