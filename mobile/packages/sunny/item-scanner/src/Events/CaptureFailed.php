<?php

namespace Sunny\ItemScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The capture camera couldn't open. The message is written for the user.
 */
class CaptureFailed
{
    use Dispatchable;

    public function __construct(
        public string $id,
        public string $message,
    ) {}
}
