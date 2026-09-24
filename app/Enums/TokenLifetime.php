<?php

declare(strict_types=1);

namespace App\Enums;

use Carbon\CarbonInterface;
use Laravel\Sanctum\PersonalAccessToken;

enum TokenLifetime: string
{
    case ThirtyDays = '30';
    case NinetyDays = '90';
    case OneYear = '365';
    case Never = 'never';

    public static function fromToken(PersonalAccessToken $token): self
    {
        if ($token->expires_at === null) {
            return self::Never;
        }

        $days = (int) round($token->created_at->diffInSeconds($token->expires_at) / 86400);

        return self::tryFrom((string) $days) ?? self::ThirtyDays;
    }

    public function expiresAt(): ?CarbonInterface
    {
        return $this === self::Never ? null : now()->addDays((int) $this->value);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::ThirtyDays => __('30 days'),
            self::NinetyDays => __('90 days'),
            self::OneYear => __('1 year'),
            self::Never => __('Never'),
        };
    }
}
