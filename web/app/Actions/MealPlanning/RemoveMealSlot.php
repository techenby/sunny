<?php

declare(strict_types=1);

namespace App\Actions\MealPlanning;

use App\Models\MealSlot;
use Illuminate\Support\Facades\DB;

/**
 * Removes a slot from the template and from days that haven't been saved to
 * history yet. Saved days keep what was planned, so the slot is soft deleted.
 */
class RemoveMealSlot
{
    public function handle(MealSlot $slot): void
    {
        $savedThrough = $slot->team->mealsSavedThrough()->toDateString();

        DB::transaction(function () use ($slot, $savedThrough): void {
            $slot->templateItems()->delete();
            $slot->planSlots()->where('date', '>', $savedThrough)->delete();
            $slot->delete();
        });
    }
}
