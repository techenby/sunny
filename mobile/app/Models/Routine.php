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
use Illuminate\Database\Eloquent\Relations\HasMany;
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

    public function steps(): HasMany
    {
        return $this->hasMany(RoutineStep::class)->orderBy('position')->orderBy('id');
    }

    public function scheduleSummary(): string
    {
        return match ($this->frequency) {
            RoutineFrequency::Daily => 'Every day',
            RoutineFrequency::Weekly => $this->weekdaySummary(),
            RoutineFrequency::Monthly => 'Monthly on the '.self::ordinal($this->day_of_month ?? 1),
        };
    }

    private function weekdaySummary(): string
    {
        $weekdays = array_map(intval(...), $this->weekdays ?? []);

        if ($weekdays === []) {
            return 'No days chosen';
        }

        return collect(CarbonImmutable::getDays())
            ->only($weekdays)
            ->map(fn (string $day): string => Str::substr($day, 0, 3))
            ->join(', ');
    }

    private static function ordinal(int $number): string
    {
        $suffix = in_array($number % 100, [11, 12, 13], true) ? 'th' : (['st', 'nd', 'rd'][$number % 10 - 1] ?? 'th');

        return $number.$suffix;
    }
}
