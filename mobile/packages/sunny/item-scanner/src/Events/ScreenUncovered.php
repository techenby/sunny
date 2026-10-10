<?php

namespace Sunny\ItemScanner\Events;

use Illuminate\Foundation\Events\Dispatchable;

class ScreenUncovered
{
    use Dispatchable;

    public function __construct(
        public string $id,
    ) {}
}
