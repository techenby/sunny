<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MealTemplateItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One meal in the team's weekly template, such as tacos for Tuesday dinner.
 * `weekday` uses Carbon's numbering (0 = Sunday), like `teams.week_start`.
 */
#[Fillable(['team_id', 'meal_slot_id', 'weekday', 'recipe_id', 'name', 'position'])]
class MealTemplateItem extends Model
{
    /** @use HasFactory<MealTemplateItemFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::creating(function (MealTemplateItem $item): void {
            $item->position ??= max(0, (int) static::query()
                ->where('meal_slot_id', $item->meal_slot_id)
                ->where('weekday', $item->weekday)
                ->max('position')) + 1;
        });
    }

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

    /** @return BelongsTo<Recipe, $this> */
    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    /**
     * The recipe's current name, or the stored name once the recipe is gone
     * or for free-text meals like "Leftovers".
     */
    public function label(): string
    {
        return $this->recipe->name ?? $this->name;
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'weekday' => 'integer',
        ];
    }
}
