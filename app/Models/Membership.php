<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TeamRole;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\Pivot;

#[Fillable(['team_id', 'user_id', 'role'])]
class Membership extends Pivot
{
    use HasFactory;

    /** @var bool */
    public $incrementing = true;

    /** @var string */
    protected $table = 'team_members';

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'role' => TeamRole::class,
        ];
    }
}
