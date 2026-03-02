<?php

declare(strict_types=1);

namespace App\Enums;

enum ItemType: string
{
    case Location = 'location';
    case Bin = 'bin';
    case Item = 'item';
}
