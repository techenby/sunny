<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CrewInvitationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CrewInvitation extends Model
{
    /** @use HasFactory<CrewInvitationFactory> */
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
