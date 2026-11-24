<?php

declare(strict_types=1);

namespace App\Support;

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;

class VerificationEmailQuota
{
    public const int PER_MINUTE = 6;

    public function __construct(private User $user, private string $surface) {}

    public function claim(): bool
    {
        return RateLimiter::hit($this->minuteKey(), 60) <= self::PER_MINUTE
            && RateLimiter::hit($this->dayKey(), 86400) <= User::VERIFICATION_EMAILS_PER_DAY;
    }

    public function isSpentForToday(): bool
    {
        return RateLimiter::attempts($this->dayKey()) >= User::VERIFICATION_EMAILS_PER_DAY;
    }

    public function availableIn(): int
    {
        return RateLimiter::availableIn($this->isSpentForToday() ? $this->dayKey() : $this->minuteKey());
    }

    private function minuteKey(): string
    {
        return "verification-emails:{$this->surface}:{$this->user->id}";
    }

    private function dayKey(): string
    {
        return "verification-emails:day:{$this->user->id}";
    }
}
