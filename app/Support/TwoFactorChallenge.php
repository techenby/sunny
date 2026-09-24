<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Contracts\Cache\Lock;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

readonly class TwoFactorChallenge
{
    private const int TTL_MINUTES = 5;

    public function __construct(
        public int $userId,
        public string $deviceName,
    ) {}

    public static function issue(User $user, string $deviceName): string
    {
        $challenge = Str::random(64);

        Cache::put(self::key($challenge), [
            'user_id' => $user->id,
            'device_name' => $deviceName,
        ], now()->addMinutes(self::TTL_MINUTES));

        return $challenge;
    }

    public static function find(string $challenge): ?self
    {
        $pending = Cache::get(self::key($challenge));

        return is_array($pending) ? new self($pending['user_id'], $pending['device_name']) : null;
    }

    public static function lock(string $challenge): Lock
    {
        return Cache::lock(self::key($challenge) . ':lock', 10);
    }

    public static function forget(string $challenge): void
    {
        Cache::forget(self::key($challenge));
    }

    private static function key(string $challenge): string
    {
        return 'api-two-factor-challenge:' . hash('sha256', $challenge);
    }

    public function user(): ?User
    {
        return User::find($this->userId);
    }
}
