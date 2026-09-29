<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnySyncCoordinator;
use Native\Mobile\Attributes\Poll;

trait ChecksSunnySync
{
    #[Poll(60000)]
    public function pollSunnySync(): void
    {
        $this->refreshLocalSyncedData();

        app(SunnySyncCoordinator::class)->dispatchIfDue();
    }

    protected function refreshLocalSyncedData(): void {}
}
