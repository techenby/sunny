<?php

declare(strict_types=1);

namespace App\Enums;

enum ContainerType: string
{
    case Location = 'location';
    case Bin = 'bin';
}
