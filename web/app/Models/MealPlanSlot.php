<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\MealPlanSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A meal slot on a specific date. Its existence means the date no longer
 * follows the weekly template for that slot, even when it has no items,
 * which is how a household skips a meal.
 */
#[Fillable(['team_id', 'meal_slot_id', 'date'])]
class MealPlanSlot extends Model
{
    /** @use HasFactory<MealPlanSlotFactory> */
    use HasFactory;

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<MealSlot, $this> */
    public function slot(): BelongsTo
    {
        return $this->belongsTo(MealSlot::class, 'meal_slot_id')->withTrashed();
    }

    /** @return HasMany<MealPlanItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(MealPlanItem::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Stored as a bare calendar date so the unique (meal_slot_id, date) lookup
     * matches on every driver.
     *
     * @return Attribute<CarbonImmutable, mixed>
     */
    protected function date(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): CarbonImmutable => CarbonImmutable::parse($value)->startOfDay(),
            set: fn (mixed $value): string => CarbonImmutable::parse($value)->toDateString(),
        );
    }
}
