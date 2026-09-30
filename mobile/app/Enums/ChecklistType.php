<?php

namespace App\Enums;

use App\Icons\Android;
use App\Icons\Ios;

enum ChecklistType: string
{
    case Todo = 'todo';
    case Shopping = 'shopping';
    case Wishlist = 'wishlist';

    public function label(): string
    {
        return match ($this) {
            self::Todo => 'To-do',
            self::Shopping => 'Shopping',
            self::Wishlist => 'Wish List',
        };
    }

    public function iosIcon(): Ios
    {
        return match ($this) {
            self::Todo => Ios::CheckmarkCircle,
            self::Shopping => Ios::Cart,
            self::Wishlist => Ios::Gift,
        };
    }

    public function androidIcon(): Android
    {
        return match ($this) {
            self::Todo => Android::CheckCircle,
            self::Shopping => Android::ShoppingCart,
            self::Wishlist => Android::CardGiftcard,
        };
    }

    public function iconColor(): string
    {
        return match ($this) {
            self::Todo => 'green-500',
            self::Shopping => 'purple-500',
            self::Wishlist => 'pink-500',
        };
    }
}
