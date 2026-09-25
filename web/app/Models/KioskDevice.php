<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\KioskDeviceFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'uuid',
    'pairing_code',
    'name',
    'user_agent',
    'last_ip',
    'user_id',
    'team_id',
    'paired_at',
    'expires_at',
    'last_seen_at',
])]
class KioskDevice extends Model
{
    /** @use HasFactory<KioskDeviceFactory> */
    use HasFactory;

    public const PAIRING_ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

    public const PAIRING_CODE_LENGTH = 8;

    public const PAIRING_TTL_MINUTES = 15;

    public static function generatePairingCode(): string
    {
        $code = '';

        for ($i = 0; $i < self::PAIRING_CODE_LENGTH; $i++) {
            $code .= self::PAIRING_ALPHABET[random_int(0, strlen(self::PAIRING_ALPHABET) - 1)];
        }

        return $code;
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Team, $this> */
    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function isPaired(): bool
    {
        return $this->paired_at !== null;
    }

    /** @param Builder<KioskDevice> $query */
    #[Scope]
    protected function pending(Builder $query): void
    {
        $query->whereNull('paired_at')->where('expires_at', '>', now());
    }

    /** @param Builder<KioskDevice> $query */
    #[Scope]
    protected function paired(Builder $query): void
    {
        $query->whereNotNull('paired_at');
    }

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'paired_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
