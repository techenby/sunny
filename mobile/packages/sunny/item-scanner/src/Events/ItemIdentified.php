<?php

namespace Sunny\ItemScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The on-device model finished identifying the item in a photo.
 */
class ItemIdentified
{
    use Dispatchable;

    /**
     * @param  array{name: string, category: string, brand: string|null, model: string|null, quantity: int}  $item
     */
    public function __construct(
        public string $id,
        public array $item = [],
    ) {}
}
