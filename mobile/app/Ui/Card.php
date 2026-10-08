<?php

namespace App\Ui;

class Card
{
    public static function classes(string $variant = 'default'): string
    {
        return 'rounded-[18] '.match ($variant) {
            'filled' => 'bg-theme-surface-variant',
            'outline' => 'border border-theme-on-surface/15',
            default => 'border border-theme-on-surface/10 bg-theme-surface',
        };
    }
}
