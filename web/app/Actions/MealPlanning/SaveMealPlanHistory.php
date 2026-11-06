<?php

declare(strict_types=1);

namespace App\Actions\MealPlanning;

use App\Models\Team;

/**
 * Saves the template's meals as real entries for every day that has ended
 * since the last run, so history no longer changes when the template does.
 * Days missed while the scheduler was down are caught up on the next run.
 */
class SaveMealPlanHistory
{
    public function __construct(private OverrideMealSlot $override) {}

    /**
     * @return int the number of days saved
     */
    public function handle(Team $team): int
    {
        $yesterday = $team->today()->subDay();
        $from = $team->mealsSavedThrough()->addDay();

        if ($from->gt($yesterday)) {
            return 0;
        }

        $slots = $team->mealSlots()->get();
        $days = 0;

        foreach ($from->toPeriod($yesterday) as $date) {
            foreach ($slots as $slot) {
                $this->override->handle($slot, $date);
            }

            $team->update(['meals_saved_through' => $date->toDateString()]);
            $days++;
        }

        return $days;
    }

    /**
     * @return int the number of days saved across every team
     */
    public function all(): int
    {
        return Team::query()
            ->lazyById()
            ->sum(fn (Team $team): int => $this->handle($team));
    }
}
