<?php

namespace App\Models;

use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoutineStep extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function scopeForCurrentServer(Builder $query): void
    {
        $query->where('server', SunnyStore::server());
    }

    public function routine(): BelongsTo
    {
        return $this->belongsTo(Routine::class);
    }
}
