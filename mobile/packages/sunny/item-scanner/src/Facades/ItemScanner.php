<?php

namespace Sunny\ItemScanner\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array{available: bool, reason: string|null} availability()
 * @method static string identify(string $path, array $knownNames = [], ?string $place = null, ?string $id = null)
 *
 * @see \Sunny\ItemScanner\ItemScanner
 */
class ItemScanner extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return \Sunny\ItemScanner\ItemScanner::class;
    }
}
