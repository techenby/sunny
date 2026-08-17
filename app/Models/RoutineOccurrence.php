<?php

declare(strict_types=1);

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\RoutineOccurrenceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['routine_id', 'due_on', 'generated_at'])]
class RoutineOccurrence extends Model
{
    /** @use HasFactory<RoutineOccurrenceFactory> */
    use HasFactory;

    /** @return BelongsTo<Routine, $this> */
    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }

    /** @return HasMany<RoutineOccurrenceStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(RoutineOccurrenceStep::class);
    }

    public function isComplete(): bool
    {
        return $this->steps()->exists()
            && ! $this->steps()->whereNull('completed_at')->exists();
    }

    public function progress(): int
    {
        $total = $this->steps()->count();

        if ($total === 0) {
            return 0;
        }

        return (int) round($this->steps()->whereNotNull('completed_at')->count() / $total * 100);
    }

    /** @param Builder<RoutineOccurrence> $query */
    #[Scope]
    protected function due(Builder $query, mixed $date): void
    {
        // A plain equality against the stored date, rather than whereDate's
        // "due_on"::date cast, so the index is matched cleanly.
        $query->where('due_on', CarbonImmutable::parse($date)->toDateString());
    }

    /**
     * Stored as a bare calendar date rather than a datetime, so the unique
     * lookup on (routine_id, due_on) matches on every driver.
     */
    protected function dueOn(): Attribute
    {
        return Attribute::make(
            get: fn (string $value): CarbonImmutable => CarbonImmutable::parse($value)->startOfDay(),
            set: fn (mixed $value): string => CarbonImmutable::parse($value)->toDateString(),
        );
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'generated_at' => 'datetime',
        ];
    }
}
