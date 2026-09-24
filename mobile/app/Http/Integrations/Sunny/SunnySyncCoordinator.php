<?php

namespace App\Http\Integrations\Sunny;

use Illuminate\Support\Facades\Cache;
use Native\Mobile\AsyncTask;
use Native\Mobile\PendingAsyncTask;
use RuntimeException;

class SunnySyncCoordinator
{
    private const LOCK_KEY = 'sunny-sync';

    private const LOCK_SECONDS = 120;

    public function __construct(
        private readonly SunnySync $sync,
        private readonly SunnyTokenStore $tokens,
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
     * Start a shared async sync using the token captured on the UI thread.
     *
     * @param  callable(): void  $finished
     * @param  callable(\Throwable): void  $failed
     */
    public function dispatch(callable $finished, callable $failed): ?PendingAsyncTask
    {
        try {
            $token = $this->tokens->get();
        } catch (RuntimeException) {
            return null;
        }

        if ($token === null) {
            return null;
        }

        return AsyncTask::dispatch(static fn (): bool => app(self::class)->sync($token))
            ->finished($finished)
            ->failed($failed);
    }
}
