<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegoPiece extends Model
{
    /** @use HasFactory<\Database\Factories\LegoPieceFactory> */
    use HasFactory;

    protected $guarded = [];

    public function scopeForGroup(Builder $query, LegoGroup $group): void
    {
        $query->where('group_id', $group->id);
    }
}
