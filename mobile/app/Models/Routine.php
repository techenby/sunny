<?php

namespace App\Models;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

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
            'steps' => 'array',
            'created_at' => 'immutable_datetime',
            'updated_at' => 'immutable_datetime',
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

    /**
     * Mirrors Routine::scheduleSummary() in the web app, so a routine edited
     * on this phone reads right before it syncs.
     */
    public function scheduleSummary(): string
    {
        return match ($this->frequency) {
            RoutineFrequency::Daily => 'Every day',
            RoutineFrequency::Weekly => collect(CarbonImmutable::getDays())
                ->only($this->weekdays ?? [])
                ->map(fn (string $day): string => Str::substr($day, 0, 3))
                ->join(', ') ?: 'No days chosen',
            RoutineFrequency::Monthly => 'Monthly on the '.$this->ordinal($this->day_of_month ?? 1),
        };
    }

    /**
     * Number::ordinal() needs the intl extension, which the phone's PHP may not have.
     */
    private function ordinal(int $day): string
    {
        $suffix = in_array($day % 100, [11, 12, 13], true) ? 'th' : (['th', 'st', 'nd', 'rd'][$day % 10] ?? 'th');

        return $day.$suffix;
    }
}
