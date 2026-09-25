<?php

declare(strict_types=1);

namespace App\Features;

use Laravel\Pennant\Attributes\Name;

#[Name('inventory')]
class Inventory
{
    public function resolve(mixed $scope): bool
    {
        return true;
    }
}
