<?php

namespace App\Models;

use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineOccurrenceStep extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['completed_at' => 'immutable_datetime'];
    }

    public function scopeForCurrentServer(Builder $query): void
    {
        $query->where('server', SunnyStore::server());
    }

    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(RoutineOccurrence::class, 'routine_occurrence_id');
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }
}
