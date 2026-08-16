<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}/routines')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->name('routines')
    ->group(function (): void {
        Route::livewire('/', 'pages::routines.index')->name('.index')->middleware('can:viewAny,App\Models\Routine');
        Route::livewire('{routine}', 'pages::routines.show')->name('.show')->middleware('can:view,routine');
    });
