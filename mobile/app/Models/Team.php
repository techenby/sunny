<?php

namespace App\Models;

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
}
