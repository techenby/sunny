<?php

namespace Sunny\ItemScanner\Facades;

use Illuminate\Support\Facades\Facade;

/**
 * @method static array{available: bool, reason: string|null} availability()
 * @method static string identify(string $path, ?string $place = null, ?string $batch = null, array $batchNames = [], ?string $id = null)
 * @method static string capture(?string $id = null, bool $single = false)
 * @method static bool whenUncovered(string $id)
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
