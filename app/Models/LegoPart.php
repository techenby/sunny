<?php

namespace App\Models;

use Database\Factories\LegoPieceFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Laravel\Scout\Searchable;

class LegoPart extends Model
{
    /** @use HasFactory<LegoPieceFactory> */
    use HasFactory;
    use Searchable;

    protected $guarded = [];

    public function scopeForGroup(Builder $query, LegoGroup $group): void
    {
        $query->where('group_id', $group->id);
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(LegoGroup::class, 'group_id');
    }

    /** @return array<string, mixed> */
    public function toSearchableArray()
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'image' => $this->image,
            'created_at' => $this->created_at->timestamp,
        ];
    }
}
