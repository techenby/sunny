<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemType: string
{
    case Location = 'location';
    case Bin = 'bin';
    case Item = 'item';

    public function getIcon(): string
    {
        return match ($this) {
            self::Location => 'map-pin',
            self::Bin => 'archive-box',
            self::Item => 'cube'
        };
    }

    public function getIconColor(): string
    {
        return match ($this) {
            self::Location => 'red',
            self::Bin => 'orange',
            self::Item => 'amber'
        };
    }
}
