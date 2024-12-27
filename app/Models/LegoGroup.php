<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegoGroup extends Model
{
    /** @use HasFactory<\Database\Factories\LegoGroupFactory> */
    use HasFactory;

    protected $guarded = [];

    public function scopeParents(Builder $query): void
    {
        $query->whereNull('parent_id');
    }

    public function scopeForParent(Builder $query, LegoGroup $group): void
    {
        $query->where('parent_id', $group->id);
    }

    protected function casts()
    {
        return [
            'has_pieces' => 'boolean',
        ];
    }
}
