<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\RoutineOccurrenceStepFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['routine_occurrence_id', 'routine_step_id'])]
class RoutineOccurrenceStep extends Model
{
    /** @use HasFactory<RoutineOccurrenceStepFactory> */
    use HasFactory;

    /** @return BelongsTo<User, $this> */
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'completed_by');
    }

    /** @return BelongsTo<RoutineOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RoutineOccurrence::class, 'routine_occurrence_id');
    }

    /**
     * Kept trashed-inclusive so a past day still renders steps that have since
     * been removed from the routine.
     *
     * @return BelongsTo<RoutineStep, $this>
     */
    public function step(): BelongsTo
    {
        return $this->belongsTo(RoutineStep::class, 'routine_step_id')->withTrashed();
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    public function complete(?User $user = null): void
    {
        $this->update([
            'completed_at' => now(),
            'completed_by' => $user?->id,
        ]);
    }

    public function uncomplete(): void
    {
        $this->update([
            'completed_at' => null,
            'completed_by' => null,
        ]);
    }

    public function toggle(?User $user = null): void
    {
        $this->isCompleted() ? $this->uncomplete() : $this->complete($user);
    }

    /** @param Builder<RoutineOccurrenceStep> $query */
    #[Scope]
    protected function completed(Builder $query): void
    {
        $query->whereNotNull('completed_at');
    }

    /** @param Builder<RoutineOccurrenceStep> $query */
    #[Scope]
    protected function incomplete(Builder $query): void
    {
        $query->whereNull('completed_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
        ];
    }
}
