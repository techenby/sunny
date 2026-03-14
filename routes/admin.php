<?php

use Illuminate\Support\Facades\Route;

Route::middleware(['auth', 'verified', 'can:admin'])
    ->prefix('admin')
    ->group(function (): void {
        Route::livewire('/', 'pages::admin.dashboard')
            ->name('admin.dashboard');
    });
