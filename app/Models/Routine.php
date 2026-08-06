<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RoutineCadence;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Database\Factories\RoutineFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['team_id', 'name', 'cadence', 'reset_weekday'])]
class Routine extends Model
{
    /** @use HasFactory<RoutineFactory> */
    use HasFactory;

    public function periodStartedOn(CarbonImmutable $date): CarbonImmutable
    {
        $date = $date->startOfDay();

        if ($this->cadence === RoutineCadence::Daily) {
            return $date;
        }

        return $date->startOfWeek($this->reset_weekday ?? CarbonInterface::SUNDAY);
    }

    /** @return HasMany<RoutineTask, $this> */
    public function tasks(): HasMany
    {
        return $this->hasMany(RoutineTask::class)->orderBy('order');
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'cadence' => RoutineCadence::class,
            'reset_weekday' => 'integer',
        ];
    }
}
