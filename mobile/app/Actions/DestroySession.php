<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTokenStore;

class DestroySession
{
    public function __construct(
        private readonly SunnyStore $store,
        private readonly SunnyTokenStore $tokens,
    ) {}

    public function handle(): void
    {
        $this->tokens->forget();
        $this->store->clear();
    }
}
