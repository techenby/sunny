<?php

namespace Sunny\ItemScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The capture camera's "+1" was tapped: count another copy of the last
 * photo it sent, without a new photo.
 */
class ItemRepeated
{
    use Dispatchable;

    public function __construct(
        public string $id,
    ) {}
}
