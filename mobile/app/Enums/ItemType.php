<?php

namespace App\Enums;

use App\Icons\Android;
use App\Icons\Ios;

/**
 * Mirrors App\Enums\ItemType in the sunnyhome.app web app.
 */
enum ItemType: string
{
    case Location = 'location';
    case Bin = 'bin';
    case Item = 'item';

    public function label(): string
    {
        return match ($this) {
            self::Location => 'Location',
            self::Bin => 'Bin',
            self::Item => 'Item',
        };
    }

    public function iosIcon(): Ios
    {
        return match ($this) {
            self::Location => Ios::Mappin,
            self::Bin => Ios::Archivebox,
            self::Item => Ios::Cube,
        };
    }

    public function androidIcon(): Android
    {
        return match ($this) {
            self::Location => Android::Place,
            self::Bin => Android::Inventory2,
            self::Item => Android::ViewInAr,
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Location => 'red',
            self::Bin => 'orange',
            self::Item => 'amber',
        };
    }

    public function iconColor(): string
    {
        return $this->color().'-500';
    }
}
