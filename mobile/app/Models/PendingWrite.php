<?php

namespace App\Models;

use App\Http\Integrations\Sunny\SunnyStore;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class PendingWrite extends Model
{
    protected $table = 'sunny_pending_writes';

    public $timestamps = false;

    protected $guarded = [];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array'];
    }

    public function scopeForCurrentServer(Builder $query): void
    {
        $query->where('server', SunnyStore::server());
    }

    public function scopeFor(Builder $query, string $resource, int $recordId): void
    {
        $query->forCurrentServer()->where('resource', $resource)->where('record_id', $recordId);
    }

    public function isCreate(): bool
    {
        return $this->record_id < 0;
    }
}
