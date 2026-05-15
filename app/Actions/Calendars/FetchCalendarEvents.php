<?php

declare(strict_types=1);

namespace App\Actions\Calendars;

use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Sabre\VObject\Component\VEvent;
use Sabre\VObject\DateTimeParser;
use Sabre\VObject\Reader;

class FetchCalendarEvents
{
    /**
     * @return Collection<int, array{
     *     feed_id: int,
     *     feed_name: string,
     *     feed_color: string,
     *     title: string,
     *     location: string|null,
     *     starts_at: CarbonImmutable,
     *     ends_at: CarbonImmutable|null,
     *     all_day: bool
     *     response_status: string|null
     * }>
     */
    public function handle(CalendarFeed $feed, int $days = 30, ?CarbonImmutable $from = null): Collection
    {
        $body = Cache::remember(
            key: "calendar-feed:{$feed->id}:" . md5($feed->url),
            ttl: now()->addMinutes(15),
            callback: fn () => Http::withHeaders([
                'Accept' => 'text/calendar,text/plain,*/*',
                'User-Agent' => 'SunnyCalendar/1.0',
            ])->timeout(10)->get($feed->url)->throw()->body(),
        );

        return $this->parse($body, $feed, $from ?? CarbonImmutable::now($this->timezoneName($feed)), $days);
    }

    /**
     * @return Collection<int, array{
     *     feed_id: int,
     *     feed_name: string,
     *     feed_color: string,
     *     title: string,
     *     location: string|null,
     *     starts_at: CarbonImmutable,
     *     ends_at: CarbonImmutable|null,
     *     all_day: bool
     *     response_status: string|null
     * }>
     */
    public function parse(string $ics, CalendarFeed $feed, CarbonImmutable $from, int $days = 30): Collection
    {
        $timezone = new DateTimeZone($this->timezoneName($feed));
        $from = $from->setTimezone($timezone);
        $until = $from->addDays($days);
        $calendar = Reader::read($ics);
        $expandedCalendar = $calendar->expand($from, $until, $timezone);

        return collect($expandedCalendar->select('VEVENT'))
            ->filter(fn ($event) => $event instanceof VEvent && isset($event->DTSTART))
            ->map(fn (VEvent $event) => $this->eventData($event, $feed, $timezone))
            ->sortBy('starts_at')
            ->values();
    }

    /**
     * @return array{
     *     feed_id: int,
     *     feed_name: string,
     *     feed_color: string,
     *     title: string,
     *     location: string|null,
     *     starts_at: CarbonImmutable,
     *     ends_at: CarbonImmutable|null,
     *     all_day: bool
     *     response_status: string|null
     * }
     */
    private function eventData(VEvent $event, CalendarFeed $feed, DateTimeZone $timezone): array
    {
        $responseStatus = $this->responseStatus($event, $feed);

        return [
            'feed_id' => $feed->id,
            'feed_name' => $feed->name,
            'feed_color' => $feed->color,
            'title' => blank((string) ($event->SUMMARY ?? '')) ? __('Untitled event') : (string) $event->SUMMARY,
            'location' => blank((string) ($event->LOCATION ?? '')) ? null : (string) $event->LOCATION,
            'starts_at' => $this->carbon($event->DTSTART->getDateTime($timezone), $timezone),
            'ends_at' => $this->endDate($event, $timezone),
            'all_day' => ! $event->DTSTART->hasTime(),
            'response_status' => $responseStatus,
        ];
    }

    private function endDate(VEvent $event, DateTimeZone $timezone): ?CarbonImmutable
    {
        if (isset($event->DTEND)) {
            return $this->carbon($event->DTEND->getDateTime($timezone), $timezone);
        }

        if (isset($event->DURATION)) {
            return $this->carbon($event->DTSTART->getDateTime($timezone), $timezone)
                ->add(DateTimeParser::parseDuration((string) $event->DURATION));
        }

        return null;
    }

    private function carbon(DateTimeInterface $dateTime, DateTimeZone $timezone): CarbonImmutable
    {
        return CarbonImmutable::instance($dateTime)->setTimezone($timezone);
    }

    private function responseStatus(VEvent $event, CalendarFeed $feed): ?string
    {
        if (! isset($event->ATTENDEE)) {
            return null;
        }

        $emails = $this->teamMemberEmails($feed);

        if ($emails->isEmpty()) {
            return null;
        }

        foreach ($event->ATTENDEE as $attendee) {
            $attendeeEmail = mb_strtolower(preg_replace('/^mailto:/i', '', (string) $attendee->getValue()));

            if (! $emails->contains($attendeeEmail)) {
                continue;
            }

            return isset($attendee['PARTSTAT'])
                ? strtoupper((string) $attendee['PARTSTAT'])
                : null;
        }

        return null;
    }

    /** @return Collection<int, string> */
    private function teamMemberEmails(CalendarFeed $feed): Collection
    {
        $feed->loadMissing('team.members');

        return $feed->team->members
            ->pluck('email')
            ->map(fn (string $email): string => mb_strtolower($email))
            ->filter()
            ->values();
    }

    private function timezoneName(CalendarFeed $feed): string
    {
        return $feed->team?->timezone ?: 'America/Chicago';
    }
}
