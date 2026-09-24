<?php

namespace Sunny\ItemScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The on-device model finished identifying the items in a photo.
 */
class ItemsIdentified
{
    use Dispatchable;

    /**
     * @param  list<array{name: string, category: string, brand: string|null, model: string|null, quantity: int, existingMatch: string|null}>  $items
     */
    public function __construct(
        public string $id,
        public array $items = [],
    ) {}
}
