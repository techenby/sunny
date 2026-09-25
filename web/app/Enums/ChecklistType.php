<?php

declare(strict_types=1);

namespace App\Enums;

enum ChecklistType: string
{
    case Todo = 'todo';
    case Shopping = 'shopping';
    case Wishlist = 'wishlist';

    public function getIcon(): string
    {
        return match ($this) {
            self::Todo => 'check-circle',
            self::Shopping => 'shopping-cart',
            self::Wishlist => 'gift',
        };
    }

    public function getIconColor(): string
    {
        return match ($this) {
            self::Todo => 'green',
            self::Shopping => 'purple',
            self::Wishlist => 'pink',
        };
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Todo => __('To-do'),
            self::Shopping => __('Shopping'),
            self::Wishlist => __('Wish List'),
        };
    }
}
