<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;

class InvitationExpiry
{
    public static function days(DateTimeInterface|string|null $expiresAt): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        $expires = CarbonImmutable::parse($expiresAt)->setTimezone(config('app.timezone'));

        return $expires->isPast() ? -1 : (int) CarbonImmutable::today(config('app.timezone'))->diffInDays($expires->startOfDay());
    }

    public static function label(DateTimeInterface|string|null $expiresAt): string
    {
        return match ($days = self::days($expiresAt)) {
            null => 'Waiting to join',
            -1 => 'Expired',
            0 => 'Expires today',
            1 => 'Expires tomorrow',
            default => 'Expires in '.$days.' days',
        };
    }

    public static function isSoon(DateTimeInterface|string|null $expiresAt): bool
    {
        $days = self::days($expiresAt);

        return $days !== null && $days <= 1;
    }
}
