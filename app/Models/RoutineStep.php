<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RoutineStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['routine_id', 'name', 'position'])]
class RoutineStep extends Model
{
    /** @use HasFactory<RoutineStepFactory> */
    use HasFactory;

    /**
     * Steps are soft deleted so already-generated occurrences keep rendering
     * a truthful record of what the routine asked for on past days.
     */
    use SoftDeletes;

    protected static function booted(): void
    {
        static::creating(function (RoutineStep $step): void {
            $step->position ??= (int) static::query()
                ->where('routine_id', $step->routine_id)
                ->max('position') + 1;
        });
    }

    /** @return HasMany<RoutineOccurrenceStep, $this> */
    public function occurrenceSteps(): HasMany
    {
        return $this->hasMany(RoutineOccurrenceStep::class);
    }

    /** @return BelongsTo<Routine, $this> */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
