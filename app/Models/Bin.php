<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Bin extends Model
{
    use HasFactory;

    protected $guarded = [];

    public function location(): BelongsTo
    {
        return $this->belongsTo(Location::class);
    }
}
