<?php

namespace Sunny\ItemScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;

/**
 * The capture camera saved a photo. The camera may still be open.
 */
class PhotoCaptured
{
    use Dispatchable;

    public function __construct(
        public string $id,
        public string $path,
    ) {}
}
