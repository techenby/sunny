<?php

namespace App\Models;

use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RoutineOccurrence extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['time_of_day' => TimeOfDay::class];
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
        return $this->hasMany(RoutineOccurrenceStep::class)->orderBy('position')->orderBy('id');
    }
}
