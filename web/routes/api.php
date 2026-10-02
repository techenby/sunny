<?php

use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\ChecklistItemController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\RoutineController;
use App\Http\Controllers\Api\RoutineOccurrenceController;
use App\Http\Controllers\Api\RoutineOccurrenceStepController;
use App\Http\Controllers\Api\RoutineStepController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TokenController;
use App\Http\Middleware\EnsureTeamMembership;
use App\Http\Resources\UserResource;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('sanctum/token', [TokenController::class, 'store'])->name('api.token');

Route::post('sanctum/token/two-factor', [TokenController::class, 'twoFactor'])
    ->middleware('throttle:api-two-factor')
    ->name('api.token.two-factor');

Route::middleware('auth:sanctum')
    ->name('api.')
    ->group(function (): void {
        Route::get('user', fn (Request $request): UserResource => UserResource::make($request->user()))->name('user');
        Route::get('sync', SyncController::class)->name('sync');
        Route::post('sanctum/token/refresh', [TokenController::class, 'refresh'])->name('token.refresh');
        Route::post('logout', [TokenController::class, 'destroy'])->name('logout');

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

                Route::get('checklists', [ChecklistController::class, 'index'])->name('checklists.index');
                Route::post('checklists', [ChecklistController::class, 'store'])->name('checklists.store');
                Route::get('checklists/{checklist}', [ChecklistController::class, 'show'])->name('checklists.show');
                Route::patch('checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
                Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');

                Route::get('checklists/{checklist}/items', [ChecklistItemController::class, 'index'])->name('checklists.items.index');
                Route::post('checklists/{checklist}/items', [ChecklistItemController::class, 'store'])->name('checklists.items.store');
                Route::get('checklists/{checklist}/items/{item}', [ChecklistItemController::class, 'show'])->name('checklists.items.show');
                Route::patch('checklists/{checklist}/items/{item}', [ChecklistItemController::class, 'update'])->name('checklists.items.update');
                Route::delete('checklists/{checklist}/items/{item}', [ChecklistItemController::class, 'destroy'])->name('checklists.items.destroy');

                Route::get('routines', [RoutineController::class, 'index'])->name('routines.index');
                Route::post('routines', [RoutineController::class, 'store'])->name('routines.store');
                Route::get('routines/{routine}', [RoutineController::class, 'show'])->name('routines.show');
                Route::patch('routines/{routine}', [RoutineController::class, 'update'])->name('routines.update');
                Route::delete('routines/{routine}', [RoutineController::class, 'destroy'])->name('routines.destroy');

                Route::post('routines/{routine}/steps', [RoutineStepController::class, 'store'])->name('routines.steps.store');
                Route::patch('routines/{routine}/steps/{step}', [RoutineStepController::class, 'update'])->name('routines.steps.update');
                Route::delete('routines/{routine}/steps/{step}', [RoutineStepController::class, 'destroy'])->name('routines.steps.destroy');

                Route::get('routine-occurrences', [RoutineOccurrenceController::class, 'index'])->name('routine-occurrences.index');
                Route::patch('routine-occurrences/{routineOccurrence}/steps/{step}', [RoutineOccurrenceStepController::class, 'update'])->name('routine-occurrences.steps.update');
            });
    });
