<?php

namespace Sunny\ItemScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The on-device model couldn't identify the items in a photo. The message
 * is written for the user.
 */
class IdentificationFailed
{
    use Dispatchable;

    public function __construct(
        public string $id,
        public string $message,
    ) {}
}
