<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MealSlotFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['team_id', 'name', 'position'])]
class MealSlot extends Model
{
    /** @use HasFactory<MealSlotFactory> */
    use HasFactory;

    /**
     * Slots are soft deleted so days already saved to history keep showing
     * the meals that were planned in a slot the household later removed.
     */
    use SoftDeletes;

    public const array DEFAULTS = ['Breakfast', 'Lunch', 'Dinner'];

    protected static function booted(): void
    {
        static::creating(function (MealSlot $slot): void {
            $slot->position ??= max(0, (int) static::withTrashed()
                ->where('team_id', $slot->team_id)
                ->max('position')) + 1;
        });
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return HasMany<MealTemplateItem, $this> */
    public function templateItems(): HasMany
    {
        return $this->hasMany(MealTemplateItem::class)->orderBy('position')->orderBy('id');
    }

    /** @return HasMany<MealPlanSlot, $this> */
    public function planSlots(): HasMany
    {
        return $this->hasMany(MealPlanSlot::class);
    }
}
