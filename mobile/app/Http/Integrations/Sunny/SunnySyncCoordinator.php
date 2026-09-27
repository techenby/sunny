<?php

namespace App\Http\Integrations\Sunny;

use Illuminate\Support\Facades\Cache;
use Native\Mobile\AsyncTask;
use RuntimeException;

class SunnySyncCoordinator
{
    private const LOCK_KEY = 'sunny-sync';

    private const LOCK_SECONDS = 120;

    private const DISPATCH_KEY = 'sunny-sync-dispatched';

    private const DISPATCH_SECONDS = 300;

    public function __construct(
        private readonly SunnySync $sync,
        private readonly SunnyTokenStore $tokens,
        private readonly SunnyStore $store,
    ) {}

    /**
     * Run one sync if another foreground or background sync is not already active.
     *
     * @return bool Whether this invocation performed a sync.
     */
    public function sync(#[\SensitiveParameter] ?string $token = null): bool
    {
        $lock = Cache::lock(self::LOCK_KEY, self::LOCK_SECONDS);

        if (! $lock->get()) {
            return false;
        }

        try {
            $this->sync->sync($token);

            return true;
        } finally {
            $lock->release();
        }
    }

    /**
     * Start a shared async sync when local data is stale and no background sync is in flight.
     * The marker is only cleared on success, so a failed sync backs off until it expires.
     */
    public function dispatchIfStale(): void
    {
        if (! $this->store->isStale() || ! Cache::add(self::DISPATCH_KEY, true, self::DISPATCH_SECONDS)) {
            return;
        }

        try {
            $token = $this->tokens->get();
        } catch (RuntimeException) {
            $token = null;
        }

        if ($token === null) {
            Cache::forget(self::DISPATCH_KEY);

            return;
        }

        AsyncTask::dispatch(static function () use ($token): bool {
            $synced = app(self::class)->sync($token);
            Cache::forget(self::DISPATCH_KEY);

            return $synced;
        })->shared('sunny-sync-complete');
    }
}
