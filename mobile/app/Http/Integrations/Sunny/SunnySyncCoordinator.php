<?php

namespace App\Http\Integrations\Sunny;

use Illuminate\Support\Facades\Cache;
use Native\Mobile\AsyncTask;
use RuntimeException;

class SunnySyncCoordinator
{
    public const string REFRESH_ADVICE = 'Pull down on Summary to refresh.';

    public const string NO_TEAM_ADVICE = 'Select a team in Settings. If none are listed, pull down on Summary to refresh.';

    private const LOCK_KEY = 'sunny-sync';

    private const LOCK_SECONDS = 120;

    private const DISPATCH_KEY = 'sunny-sync-dispatched';

    private const DISPATCH_SECONDS = 300;

    public function __construct(
        private readonly SunnySync $sync,
        private readonly SunnyWrites $writes,
        private readonly SunnyOutbox $outbox,
        private readonly SunnyTokenStore $tokens,
        private readonly SunnyStore $store,
    ) {}

    /**
     * Send queued changes and then download, if another foreground or background sync is not already active.
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
            $this->ensureEmailIsVerified($token);
            $this->writes->push($token);
            $this->sync->sync($token);
            rescue(fn () => app(SunnyTeams::class)->refreshInvitations($token), report: false);

            return true;
        } catch (EmailVerificationRequired $exception) {
            $this->store->requireEmailVerification();

            throw $exception;
        } finally {
            $lock->release();
        }
    }

    private function ensureEmailIsVerified(#[\SensitiveParameter] ?string $token): void
    {
        if ($this->store->profile()['verified'] ?? true) {
            return;
        }

        rescue(fn () => app(SunnyAccount::class)->refresh($token), report: false);

        throw_if($this->store->needsEmailVerification(), new EmailVerificationRequired);
    }

    public function isDue(): bool
    {
        return $this->store->isStale() || $this->outbox->hasPending();
    }

    public function dispatchIfDue(): void
    {
        if ($this->isDue()) {
            $this->dispatch();
        }
    }

    /**
     * Start a shared async sync unless a background sync is in flight.
     * A failed sync leaves the marker in place, so retries back off until it expires or a new session starts.
     */
    public function dispatch(): void
    {
        if (! Cache::add(self::DISPATCH_KEY, true, self::DISPATCH_SECONDS)) {
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

    public static function forgetDispatch(): void
    {
        Cache::forget(self::DISPATCH_KEY);
    }
}
