<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class LegoBin extends Model
{
    /** @use HasFactory<\Database\Factories\LegoBinFactory> */
    use HasFactory;

    public function pieces(): BelongsToMany
    {
        return $this->belongsToMany(LegoPiece::class, 'lego_bin_piece', 'piece_id', 'bin_id');
    }
}
