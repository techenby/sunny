<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MealPlanItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['meal_plan_slot_id', 'recipe_id', 'name', 'position'])]
class MealPlanItem extends Model
{
    /** @use HasFactory<MealPlanItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (MealPlanItem $item): void {
            $item->position ??= max(0, (int) static::query()
                ->where('meal_plan_slot_id', $item->meal_plan_slot_id)
                ->max('position')) + 1;
        });
    }

    /** @return BelongsTo<MealPlanSlot, $this> */
    public function planSlot(): BelongsTo
    {
        return $this->belongsTo(MealPlanSlot::class, 'meal_plan_slot_id');
    }

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * The recipe's current name, or the name saved when the meal was planned
     * once the recipe is gone, or for free-text meals like "Leftovers".
     */
    public function label(): string
    {
        return $this->recipe->name ?? $this->name;
    }
}
