<?php

use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Middleware\EnsureTeamMembership;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('sanctum/token', [TokenController::class, 'store'])
    ->middleware('throttle:login')
    ->name('api.token');

Route::post('sanctum/token/two-factor', [TokenController::class, 'twoFactor'])
    ->middleware('throttle:api-two-factor')
    ->name('api.token.two-factor');

Route::middleware('auth:sanctum')
    ->name('api.')
    ->group(function (): void {
        Route::get('user', fn (Request $request) => $request->user())->name('user');
        Route::get('sync', SyncController::class)->name('sync');
        Route::post('sanctum/token/refresh', [TokenController::class, 'refresh'])->name('token.refresh');

        Route::prefix('teams/{team}')
            ->middleware(EnsureTeamMembership::class)
            ->scopeBindings()
            ->group(function (): void {
                Route::get('items', [ItemController::class, 'index'])->name('items.index');
                Route::post('items', [ItemController::class, 'store'])->name('items.store');
                Route::post('items/{item}/duplicate', [ItemController::class, 'duplicate'])->name('items.duplicate');
                Route::get('items/{item}', [ItemController::class, 'show'])->name('items.show');
                Route::patch('items/{item}', [ItemController::class, 'update'])->name('items.update');
                Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');

                Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
                Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store');
                Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
                Route::patch('recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
                Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
            });
    });
