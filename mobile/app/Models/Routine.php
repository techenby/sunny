<?php

namespace App\Models;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Number;

class Routine extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'time_of_day' => TimeOfDay::class,
            'frequency' => RoutineFrequency::class,
            'weekdays' => 'array',
            'is_active' => 'boolean',
        ];
    }

    public function scopeForCurrentServer(Builder $query): void
    {
        $query->where('server', SunnyStore::server());
    }

    public function scopeForActiveTeam(Builder $query): void
    {
        $query->forCurrentServer()->where('team_id', app(SunnyTeam::class)->current()?->id);
    }

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function steps(): HasMany
    {
        return $this->hasMany(RoutineStep::class)->orderBy('position')->orderBy('id');
    }

    /**
     * A human-readable version of the recurrence, matching the web app's.
     */
    public function scheduleSummary(): string
    {
        return match ($this->frequency) {
            RoutineFrequency::Daily => 'Every day',
            RoutineFrequency::Weekly => collect($this->weekdays ?? [])
                ->map(fn (mixed $day): int => (int) $day)
                ->sort()
                ->map(fn (int $day): string => RoutineFrequency::weekdayName($day))
                ->implode(', ') ?: 'No days chosen',
            RoutineFrequency::Monthly => 'Monthly on the '.Number::ordinal($this->day_of_month ?? 1),
        };
    }
}
