<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Closure;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;

class VerificationEmailLimit
{
    public const string NAME = 'verification-email';

    public const int PER_MINUTE = 6;

    public static function for(User $user): Limit
    {
        return Limit::perMinute(self::PER_MINUTE)->by("user:{$user->id}");
    }

    public static function attempt(User $user, Closure $send): bool
    {
        return RateLimiter::attempt(self::key($user), self::PER_MINUTE, $send, 60) !== false;
    }

    public static function availableIn(User $user): int
    {
        return RateLimiter::availableIn(self::key($user));
    }

    private static function key(User $user): string
    {
        return md5(self::NAME . self::for($user)->key);
    }
}
