<?php

namespace App\Concerns;

use App\Support\Offline;
use Native\Mobile\Attributes\Computed;

trait NeedsConnection
{
    #[Computed]
    public function disconnected(): bool
    {
        return Offline::now();
    }
}
