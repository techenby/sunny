<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LegoBin extends Model
{
    /** @use HasFactory<\Database\Factories\LegoBinFactory> */
    use HasFactory;

    protected $guarded = [];

    public function colors(): BelongsToMany
    {
        return $this->belongsToMany(LegoColor::class, 'lego_bin_color', 'bin_id', 'color_id');
    }

    public function pieces(): BelongsToMany
    {
        return $this->belongsToMany(LegoPiece::class, 'lego_bin_piece', 'bin_id', 'piece_id');
    }
}
