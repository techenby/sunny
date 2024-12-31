<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;

class LegoColor extends Model
{
    /** @use HasFactory<\Database\Factories\LegoColorFactory> */
    use HasFactory;
    use Searchable;

    protected $guarded = [];

    /** @return array<string, mixed> */
    public function toSearchableArray()
    {
        return [
            'id' => (string) $this->id,
            'name' => $this->name,
            'hex' => $this->hex,
            'is_trans' => $this->is_trans,
            'created_at' => $this->created_at->timestamp,
        ];
    }

    protected function casts()
    {
        return [
            'is_trans' => 'boolean',
            'external' => 'array',
        ];
    }
}
