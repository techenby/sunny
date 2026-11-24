<?php

declare(strict_types=1);

namespace App\Models;

use App\Concerns\LimitedPerOwner;
use Database\Factories\RoutineStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

#[Fillable(['routine_id', 'name', 'position', 'client_uuid'])]
class RoutineStep extends Model
{
    /** @use HasFactory<RoutineStepFactory> */
    use HasFactory;
    use LimitedPerOwner;

    public const int MAX_PER_OWNER = 50;

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

    protected function ownerColumn(): string
    {
        return 'routine_id';
    }

    protected function ownerLimitMessage(): string
    {
        return __('A routine can have up to :count steps.', ['count' => self::MAX_PER_OWNER]);
    }
}
