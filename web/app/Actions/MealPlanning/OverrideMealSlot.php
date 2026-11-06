<?php

declare(strict_types=1);

namespace App\Actions\MealPlanning;

use App\Models\MealPlanSlot;
use App\Models\MealSlot;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

/**
 * Detaches one slot on one date from the weekly template by copying the
 * template's meals into a saved row, so they can be edited without changing
 * any other date. Calling it again for the same slot and date is a no-op.
 */
class OverrideMealSlot
{
    public function handle(MealSlot $slot, CarbonInterface $date): MealPlanSlot
    {
        $date = CarbonImmutable::parse($date)->startOfDay();

        return DB::transaction(function () use ($slot, $date): MealPlanSlot {
            $planSlot = MealPlanSlot::firstOrCreate([
                'meal_slot_id' => $slot->id,
                'date' => $date->toDateString(),
            ], [
                'team_id' => $slot->team_id,
            ]);

            if (! $planSlot->wasRecentlyCreated) {
                return $planSlot;
            }

            $slot->templateItems()
                ->where('weekday', $date->dayOfWeek)
                ->with('recipe')
                ->get()
                ->each(fn ($item) => $planSlot->items()->create([
                    'recipe_id' => $item->recipe_id,
                    'name' => $item->label(),
                    'position' => $item->position,
                ]));

            return $planSlot;
        });
    }
}
