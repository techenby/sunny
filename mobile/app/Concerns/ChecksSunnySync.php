<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use Native\Mobile\Attributes\Poll;

trait ChecksSunnySync
{
    #[Poll(60000)]
    public function pollSunnySync(): void
    {
        $this->refreshLocalSyncedData();

        if (app(SunnyStore::class)->isStale()) {
            app(SunnySyncCoordinator::class)->dispatch();
        }
    }

    protected function refreshLocalSyncedData(): void {}
}
