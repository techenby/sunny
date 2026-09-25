<?php

declare(strict_types=1);

namespace App\Features;

use Laravel\Pennant\Attributes\Name;

#[Name('recipes')]
class Recipes
{
    public function resolve(mixed $scope): bool
    {
        return true;
    }
}
