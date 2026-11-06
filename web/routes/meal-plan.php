<?php

use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Support\Facades\Route;

Route::prefix('{current_team}/meal-plan')
    ->middleware(['auth', 'verified', EnsureTeamMembership::class])
    ->name('meal-plan')
    ->group(function (): void {
        Route::livewire('/', 'pages::meal-plan.index')->name('.index')->withHead(title: 'Meal plan');
        Route::livewire('template', 'pages::meal-plan.template')->name('.template')->withHead(title: 'Weekly template');
    });
