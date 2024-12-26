<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class LegoColor extends Model
{
    /** @use HasFactory<\Database\Factories\LegoColorFactory> */
    use HasFactory;

    protected $guarded = [];

    protected function casts()
    {
        return [
            'is_trans' => 'boolean',
            'external' => 'array'
        ];
    }
}
