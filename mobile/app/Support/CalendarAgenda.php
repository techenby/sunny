<?php

namespace App\Support;

use Carbon\CarbonImmutable;

class CalendarAgenda
{
    /**
     * @param  list<array<string, mixed>>  $events
     * @return list<array{title: string, location: string|null, time: string, until: string|null, color: string, declined: bool}>
     */
    public static function rows(array $events, CarbonImmutable $day): array
    {
        $dayStart = $day->startOfDay();
        $dayEnd = $dayStart->addDay();

        return array_map(function (array $event) use ($dayStart, $dayEnd): array {
            $startsAt = CarbonImmutable::parse($event['starts_at']);
            $endsAt = $event['ends_at'] !== null ? CarbonImmutable::parse($event['ends_at']) : null;
            $declined = ($event['response_status'] ?? null) === 'DECLINED';

            if ($event['all_day']) {
                [$time, $until] = ['All day', null];
            } elseif ($startsAt->lessThan($dayStart) && $endsAt !== null) {
                [$time, $until] = $endsAt->lessThanOrEqualTo($dayEnd) ? ['Until', $endsAt->format('g:i a')] : ['All day', null];
            } elseif ($endsAt === null || $endsAt->equalTo($startsAt)) {
                [$time, $until] = [$startsAt->format('g:i a'), null];
            } else {
                $sameMeridiem = $startsAt->format('a') === $endsAt->format('a') && $startsAt->isSameDay($endsAt);
                [$time, $until] = [$startsAt->format($sameMeridiem ? 'g:i' : 'g:i a'), $endsAt->format('g:i a')];
            }

            return [
                'title' => $event['title'],
                'location' => $event['location'],
                'time' => $time,
                'until' => $until,
                'color' => self::color($event['feed_color']),
                'declined' => $declined,
            ];
        }, $events);
    }

    /**
     * @param  list<array<string, mixed>>  $events
     * @return array<string, list<array<string, mixed>>>
     */
    public static function byDay(array $events, CarbonImmutable $from): array
    {
        $first = $from->toDateString();

        return collect($events)
            ->groupBy(fn (array $event): string => max($event['date'], $first))
            ->sortKeys()
            ->map(fn ($day): array => $day->values()->all())
            ->all();
    }

    public static function dayName(CarbonImmutable $day, CarbonImmutable $today): string
    {
        return match ((int) round($today->diffInDays($day))) {
            0 => 'Today',
            1 => 'Tomorrow',
            default => $day->format('l'),
        };
    }

    public static function color(string $hex): string
    {
        return preg_match('/^#[0-9a-f]{6}$/i', $hex) ? '['.strtolower($hex).']' : 'theme-outline';
    }

    public static function problem(?string $message): ?string
    {
        if ($message === null || trim($message) === '') {
            return null;
        }

        return preg_match('/HTTP (\d{3})/', $message, $matches) ? 'HTTP '.$matches[1] : rtrim(trim($message), '.');
    }

    public static function ago(CarbonImmutable $at): string
    {
        return $at->greaterThan(now()->subMinute()) ? 'just now' : $at->diffForHumans();
    }

    public static function lastUpdated(CarbonImmutable $fetchedAt, CarbonImmutable $now): string
    {
        $fetchedAt = $fetchedAt->setTimezone($now->getTimezone());

        if ($fetchedAt->isSameDay($now)) {
            return match (true) {
                $fetchedAt->hour < 12 => 'this morning at '.$fetchedAt->format('g:i'),
                $fetchedAt->hour < 17 => 'this afternoon at '.$fetchedAt->format('g:i'),
                default => 'this evening at '.$fetchedAt->format('g:i'),
            };
        }

        if ($fetchedAt->isSameDay($now->subDay())) {
            return 'yesterday at '.$fetchedAt->format('g:i a');
        }

        return $fetchedAt->format('M j').' at '.$fetchedAt->format('g:i a');
    }
}
