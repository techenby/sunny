<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Team extends Model
{
    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    public function recipes(): HasMany
    {
        return $this->hasMany(Recipe::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(Item::class);
    }

    public function checklists(): HasMany
    {
        return $this->hasMany(Checklist::class);
    }

    public function routines(): HasMany
    {
        return $this->hasMany(Routine::class);
    }

    public function today(): CarbonImmutable
    {
        return CarbonImmutable::now($this->timezone ?? config('app.timezone'))->startOfDay();
    }
}
