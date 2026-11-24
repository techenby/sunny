<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;

test('the database seeder builds the Strawhat Pirates household', function () {
    $this->seed();

    $strawhats = Team::firstWhere('slug', 'strawhat-pirates');

    expect($strawhats)->not->toBeNull()
        ->and(User::count())->toBeGreaterThan(0)
        ->and(Team::whereNull('slug')->count())->toBe(0)
        ->and(Recipe::whereNull('slug')->count())->toBe(0)
        ->and($strawhats->recipes()->count())->toBe(8)
        ->and(Recipe::whereNull('description')->count())->toBe(0)
        ->and(Recipe::whereNull('ingredients')->count())->toBe(0)
        ->and(Recipe::whereNull('instructions')->count())->toBe(0)
        ->and(Recipe::whereNull('servings')->count())->toBe(0)
        ->and(Recipe::whereNull('prep_time')->count())->toBe(0)
        ->and(Recipe::whereNull('cook_time')->count())->toBe(0)
        ->and(Recipe::whereNull('total_time')->count())->toBe(0)
        ->and(Recipe::whereNull('notes')->count())->toBe(0)
        ->and(Recipe::whereNull('nutrition')->count())->toBe(0)
        ->and(Recipe::whereJsonLength('tags', 0)->count())->toBe(0)
        ->and(Checklist::count())->toBeGreaterThan(0)
        ->and(Routine::count())->toBeGreaterThan(0)
        ->and(ChecklistItem::where('position', 0)->count())->toBe(0)
        ->and(RoutineStep::where('position', 0)->count())->toBe(0)
        ->and(TeamInvitation::whereNull('code')->count())->toBe(0);
});
