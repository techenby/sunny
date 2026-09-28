<?php

namespace App\Actions;

use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use App\Http\Integrations\Sunny\SunnyTokenStore;

class DestroySession
{
    public function __construct(
        private readonly SunnyStore $store,
        private readonly SunnyTokenStore $tokens,
    ) {}

    public function handle(): void
    {
        $this->store->clear();
        SunnySyncCoordinator::forgetDispatch();
        $this->tokens->forget();
    }
}
