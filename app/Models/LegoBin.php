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

    public function parts(): BelongsToMany
    {
        return $this->belongsToMany(LegoPart::class, 'lego_bin_part', 'bin_id', 'part_id');
    }
}
