<?php

namespace App\Concerns;

use App\Http\Integrations\Sunny\SunnyCalendar;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Http\Integrations\Sunny\SunnyTokenStore;
use App\Support\CalendarAgenda;
use Carbon\CarbonImmutable;
use Native\Mobile\AsyncTask;
use Native\Mobile\Attributes\Computed;
use RuntimeException;

trait ShowsCalendarEvents
{
    public string $calendarStatus = '';

    public string $calendarLoading = '';

    /**
     * @return array{0: int, 1: string, 2: int}|null
     */
    abstract protected function calendarRange(): ?array;

    /**
     * @return array{events: list<array<string, mixed>>, warnings: list<array{feed_id: int, feed_name: string, message: string|null}>, fetched_at: CarbonImmutable}|null
     */
    #[Computed]
    public function calendarSnapshot(): ?array
    {
        $range = $this->calendarRange();

        return $range === null ? null : app(SunnyCalendar::class)->events(...$range);
    }

    /**
     * @return array{feeds: list<array<string, mixed>>, fetched_at: CarbonImmutable}|null
     */
    #[Computed]
    public function calendarFeeds(): ?array
    {
        $range = $this->calendarRange();

        return $range === null ? null : app(SunnyCalendar::class)->feeds($range[0]);
    }

    #[Computed]
    public function calendarUpdatedAt(): ?string
    {
        $fetchedAt = $this->calendarSnapshot['fetched_at'] ?? null;
        $today = app(SunnyTeam::class)->current()?->today();

        return $fetchedAt === null || $today === null ? null : CalendarAgenda::lastUpdated($fetchedAt, CarbonImmutable::now($today->getTimezone()));
    }

    #[Computed]
    public function calendarIsLoading(): bool
    {
        $range = $this->calendarRange();

        return $range !== null && $this->calendarLoading === implode(':', $range);
    }

    public function refreshCalendar(bool $force = false): void
    {
        $range = $this->calendarRange();

        if ($range === null) {
            return;
        }

        $key = implode(':', $range);

        if (! $force && ($this->calendarLoading === $key || ! SunnyCalendar::isStale($this->calendarSnapshot))) {
            return;
        }

        [$teamId, $from, $days] = $range;

        try {
            $token = app(SunnyTokenStore::class)->get();
        } catch (RuntimeException) {
            $token = null;
        }

        $this->calendarLoading = $key;
        $this->calendarStatus = '';
        unset($this->calendarIsLoading);

        AsyncTask::dispatch(static fn (): string => app(SunnyCalendar::class)->refresh($teamId, $from, $days, $token))
            ->finished(fn (string $status) => $this->calendarLoaded($key, $status))
            ->failed(fn () => $this->calendarLoaded($key, app(SunnyCalendar::class)->refresh($teamId, $from, $days, $token, withFeeds: false)));
    }

    protected function calendarLoaded(string $key, string $status): void
    {
        if ($this->calendarLoading === $key) {
            $this->calendarLoading = '';
        }

        $range = $this->calendarRange();

        if ($range === null || implode(':', $range) !== $key) {
            return;
        }

        $this->calendarStatus = $status === 'ok' ? '' : $status;
        unset($this->calendarSnapshot, $this->calendarFeeds, $this->calendarIsLoading, $this->calendarUpdatedAt);
    }
}
