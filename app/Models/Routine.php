<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RoutineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

#[Fillable([
    'team_id',
    'user_id',
    'name',
    'time_of_day',
    'frequency',
    'weekdays',
    'day_of_month',
    'starts_on',
    'is_active',
])]
class Routine extends Model
{
    /** @use HasFactory<RoutineFactory> */
    use HasFactory;
    use SoftDeletes;

    /** @return HasMany<RoutineOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(RoutineOccurrence::class);
    }

    /** @return HasMany<RoutineStep, $this> */
    public function steps(): HasMany
    {
        return $this->hasMany(RoutineStep::class)->orderBy('position');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Whether this routine is scheduled to run on the given date.
     *
     * Dates are compared as calendar days, so the caller is responsible for
     * passing a date already resolved in the team's timezone.
     */
    public function occursOn(CarbonInterface $date): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($date->lt($this->starts_on->startOfDay())) {
            return false;
        }

        return match ($this->frequency) {
            RoutineFrequency::Daily => true,
            RoutineFrequency::Weekly => in_array($date->dayOfWeek, $this->scheduledWeekdays(), true),
            RoutineFrequency::Monthly => $date->day === $this->dayOfMonthWithin($date),
        };
    }

    /**
     * A human-readable version of the recurrence, for listing screens.
     */
    public function scheduleSummary(): string
    {
        return match ($this->frequency) {
            RoutineFrequency::Daily => __('Every day'),
            RoutineFrequency::Weekly => $this->weekdaySummary(),
            RoutineFrequency::Monthly => __('Monthly on the :day', [
                'day' => Number::ordinal($this->day_of_month ?? 1),
            ]),
        };
    }

    /**
     * The chosen weekdays as Carbon day constants, normalised because form input
     * and JSON round-trips can hand these back as strings.
     *
     * @return array<int, int>
     */
    public function scheduledWeekdays(): array
    {
        return array_map(intval(...), $this->weekdays ?? []);
    }

    /**
     * The chosen day of the month, clamped to the last day of short months so a
     * routine set for the 31st still runs in February.
     */
    public function dayOfMonthWithin(CarbonInterface $date): int
    {
        return min($this->day_of_month ?? 1, $date->daysInMonth);
    }

    /** @param Builder<Routine> $query */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /** @param Builder<Routine> $query */
    #[Scope]
    protected function forTimeOfDay(Builder $query, TimeOfDay $timeOfDay): void
    {
        $query->where('time_of_day', $timeOfDay);
    }

    /** @param  Builder<Routine>  $query */
    #[Scope]
    protected function household(Builder $query): void
    {
        $query->whereNull('user_id');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'time_of_day' => TimeOfDay::class,
            'frequency' => RoutineFrequency::class,
            'weekdays' => 'array',
            'starts_on' => 'date',
            'is_active' => 'boolean',
        ];
    }

    private function weekdaySummary(): string
    {
        $weekdays = $this->scheduledWeekdays();

        if ($weekdays === []) {
            return __('No days chosen');
        }

        return collect(CarbonImmutable::getDays())
            ->only($weekdays)
            ->map(fn (string $day): string => Str::substr($day, 0, 3))
            ->join(', ');
    }
}
