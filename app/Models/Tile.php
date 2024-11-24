<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Spatie\Dashboard\Models\Tile as SpatieTile;

class Tile extends SpatieTile
{
    use HasFactory;

    public $casts = [
        'data' => 'array',
        'settings' => 'array',
    ];
}
