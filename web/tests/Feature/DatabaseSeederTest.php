<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;

test('the database seeder builds the Strawhat Pirates household', function () {
    $this->seed();

    $strawhats = Team::firstWhere('slug', 'strawhat-pirates');

    expect($strawhats)->not->toBeNull()
        ->and(User::count())->toBeGreaterThan(0)
        ->and(Team::whereNull('slug')->count())->toBe(0)
        ->and(Recipe::whereNull('slug')->count())->toBe(0)
        ->and(Checklist::count())->toBeGreaterThan(0)
        ->and(Routine::count())->toBeGreaterThan(0)
        ->and(ChecklistItem::where('position', 0)->count())->toBe(0)
        ->and(RoutineStep::where('position', 0)->count())->toBe(0);
});
