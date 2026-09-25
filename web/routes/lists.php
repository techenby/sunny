<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}/lists')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->name('lists')
    ->group(function (): void {
        Route::livewire('/', 'pages::lists.index')->name('.index')->middleware('can:viewAny,App\Models\Checklist');
        Route::livewire('{checklist}', 'pages::lists.show')->name('.show')->middleware('can:view,checklist');
    });
