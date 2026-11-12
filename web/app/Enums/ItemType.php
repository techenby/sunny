<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemType: string
{
    case Location = 'location';
    case Bin = 'bin';
    case Item = 'item';

    public function label(): string
    {
        return match ($this) {
            self::Location => __('Place'),
            self::Bin => __('Storage'),
            self::Item => __('Thing'),
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Location => __('A room, like Garage or Shed'),
            self::Bin => __('Holds things, like a shelf or box'),
            self::Item => __('Anything you want to find later'),
        };
    }

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
