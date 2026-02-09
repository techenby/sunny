<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewInvitation extends Model
{
    /** @use HasFactory<\Database\Factories\CrewInvitationFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'email',
    ];

    /** @return BelongsTo<Crew, $this> */
    public function crew(): BelongsTo
    {
        return $this->belongsTo(Crew::class);
    }
}
