<?php

use App\Http\Controllers\Api\AccountController;
use App\Http\Controllers\Api\CalendarEventController;
use App\Http\Controllers\Api\CalendarFeedController;
use App\Http\Controllers\Api\ChecklistController;
use App\Http\Controllers\Api\ChecklistItemController;
use App\Http\Controllers\Api\InvitationController;
use App\Http\Controllers\Api\ItemController;
use App\Http\Controllers\Api\ItemPhotoController;
use App\Http\Controllers\Api\OnboardingController;
use App\Http\Controllers\Api\RecipeController;
use App\Http\Controllers\Api\RecipeImportController;
use App\Http\Controllers\Api\RecipeShareController;
use App\Http\Controllers\Api\RoutineController;
use App\Http\Controllers\Api\RoutineOccurrenceController;
use App\Http\Controllers\Api\RoutineOccurrenceStepController;
use App\Http\Controllers\Api\RoutineStepController;
use App\Http\Controllers\Api\SharedRecipeController;
use App\Http\Controllers\Api\SyncController;
use App\Http\Controllers\Api\TeamController;
use App\Http\Controllers\Api\TeamInvitationController;
use App\Http\Controllers\Api\TeamMemberController;
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
        Route::patch('user', [AccountController::class, 'update'])->name('user.update');
        Route::put('user/password', [AccountController::class, 'updatePassword'])->middleware('throttle:6,1')->name('user.password.update');
        Route::post('user/email/verification-notification', [AccountController::class, 'sendVerificationNotification'])->middleware('throttle:6,1')->name('user.verification.send');
        Route::delete('user', [AccountController::class, 'destroy'])->middleware('throttle:6,1')->name('user.destroy');
        Route::post('user/onboarding', OnboardingController::class)->name('user.onboarding');
        Route::get('sync', SyncController::class)->name('sync');
        Route::post('sanctum/token/refresh', [TokenController::class, 'refresh'])->name('token.refresh');
        Route::post('logout', [TokenController::class, 'destroy'])->name('logout');

        Route::get('shared-recipes/{recipe:share_token}', [SharedRecipeController::class, 'show'])->name('shared-recipes.show');
        Route::post('shared-recipes/{recipe:share_token}/save', [SharedRecipeController::class, 'save'])->name('shared-recipes.save');

        Route::post('teams', [TeamController::class, 'store'])->name('teams.store');
        Route::get('invitations', [InvitationController::class, 'index'])->name('invitations.index');
        Route::post('invitations/{invitation}/accept', [InvitationController::class, 'accept'])->name('invitations.accept');
        Route::post('invitations/{invitation}/decline', [InvitationController::class, 'decline'])->name('invitations.decline');

        Route::prefix('teams/{team}')
            ->middleware(EnsureTeamMembership::class)
            ->scopeBindings()
            ->group(function (): void {
                Route::patch('/', [TeamController::class, 'update'])->name('teams.update');
                Route::post('leave', [TeamController::class, 'leave'])->name('teams.leave');
                Route::patch('members/{member}', [TeamMemberController::class, 'update'])->name('teams.members.update');
                Route::delete('members/{member}', [TeamMemberController::class, 'destroy'])->name('teams.members.destroy');
                Route::get('invitations', [TeamInvitationController::class, 'index'])->name('teams.invitations.index');
                Route::post('invitations', [TeamInvitationController::class, 'store'])->name('teams.invitations.store');
                Route::delete('invitations/{invitation}', [TeamInvitationController::class, 'destroy'])->name('teams.invitations.destroy');

                Route::get('items', [ItemController::class, 'index'])->name('items.index');
                Route::post('items', [ItemController::class, 'store'])->name('items.store');
                Route::post('items/{item}/duplicate', [ItemController::class, 'duplicate'])->name('items.duplicate');
                Route::post('items/{item}/move', [ItemController::class, 'move'])->name('items.move');
                Route::get('items/{item}', [ItemController::class, 'show'])->name('items.show');
                Route::patch('items/{item}', [ItemController::class, 'update'])->name('items.update');
                Route::delete('items/{item}', [ItemController::class, 'destroy'])->name('items.destroy');
                Route::post('items/{item}/restore', [ItemController::class, 'restore'])->withTrashed()->name('items.restore');
                Route::delete('items/{item}/force', [ItemController::class, 'forceDestroy'])->withTrashed()->missing(ItemController::forceDestroyMissing(...))->name('items.force-destroy');

                Route::post('items/{item}/photos', [ItemPhotoController::class, 'store'])->name('items.photos.store');
                Route::patch('items/{item}/photos/{photo}', [ItemPhotoController::class, 'update'])->name('items.photos.update');
                Route::delete('items/{item}/photos/{photo}', [ItemPhotoController::class, 'destroy'])->name('items.photos.destroy');

                Route::get('recipes', [RecipeController::class, 'index'])->name('recipes.index');
                Route::post('recipes', [RecipeController::class, 'store'])->name('recipes.store');
                Route::post('recipes/import', RecipeImportController::class)->middleware('throttle:30,1')->name('recipes.import');
                Route::get('recipes/{recipe}', [RecipeController::class, 'show'])->name('recipes.show');
                Route::patch('recipes/{recipe}', [RecipeController::class, 'update'])->name('recipes.update');
                Route::delete('recipes/{recipe}', [RecipeController::class, 'destroy'])->name('recipes.destroy');
                Route::post('recipes/{recipe}/copy', [RecipeController::class, 'copy'])->name('recipes.copy');
                Route::put('recipes/{recipe}/share', [RecipeShareController::class, 'update'])->name('recipes.share.update');
                Route::delete('recipes/{recipe}/share', [RecipeShareController::class, 'destroy'])->name('recipes.share.destroy');

                Route::get('checklists', [ChecklistController::class, 'index'])->name('checklists.index');
                Route::post('checklists', [ChecklistController::class, 'store'])->name('checklists.store');
                Route::get('checklists/{checklist}', [ChecklistController::class, 'show'])->name('checklists.show');
                Route::patch('checklists/{checklist}', [ChecklistController::class, 'update'])->name('checklists.update');
                Route::delete('checklists/{checklist}', [ChecklistController::class, 'destroy'])->name('checklists.destroy');
                Route::post('checklists/{checklist}/clear-completed', [ChecklistController::class, 'clearCompleted'])->name('checklists.clear-completed');
                Route::post('checklists/{checklist}/reset', [ChecklistController::class, 'reset'])->name('checklists.reset');

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

                Route::get('calendar-feeds', [CalendarFeedController::class, 'index'])->name('calendar-feeds.index');
                Route::post('calendar-feeds', [CalendarFeedController::class, 'store'])->name('calendar-feeds.store');
                Route::get('calendar-feeds/{calendarFeed}', [CalendarFeedController::class, 'show'])->name('calendar-feeds.show');
                Route::patch('calendar-feeds/{calendarFeed}', [CalendarFeedController::class, 'update'])->name('calendar-feeds.update');
                Route::delete('calendar-feeds/{calendarFeed}', [CalendarFeedController::class, 'destroy'])->name('calendar-feeds.destroy');
                Route::get('calendar-events', [CalendarEventController::class, 'index'])->name('calendar-events.index');
            });
    });
