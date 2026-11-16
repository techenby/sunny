<?php

namespace App\Models;

use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ItemPhoto extends Model
{
    public const MAX_PER_ITEM = 10;

    public $incrementing = false;

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['position' => 'integer', 'created_at' => 'immutable_datetime', 'updated_at' => 'immutable_datetime'];
    }

    public function scopeForCurrentServer(Builder $query): void
    {
        $query->where('server', SunnyStore::server());
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(Item::class);
    }

    public function thumbnail(): ?string
    {
        return ($this->thumb_path !== null && is_file($this->thumb_path) ? $this->thumb_path : null) ?? $this->thumb_url ?? $this->url;
    }
}
