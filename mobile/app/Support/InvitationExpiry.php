<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use DateTimeInterface;
use Native\Mobile\Facades\Device;

class InvitationExpiry
{
    public static function days(DateTimeInterface|string|null $expiresAt): ?int
    {
        if ($expiresAt === null) {
            return null;
        }

        $today = CarbonImmutable::today(Device::getTimezone() ?? config('app.timezone'));
        $expires = CarbonImmutable::parse($expiresAt)->setTimezone($today->getTimezone());

        return $expires->isPast() ? -1 : (int) $today->diffInDays($expires->startOfDay());
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
