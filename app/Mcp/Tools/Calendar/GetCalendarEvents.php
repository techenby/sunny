<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
#[Description('Get upcoming events from the team\'s calendar feeds, grouped by date. Events include the time (or "All day"), title, calendar name, and location when available. Optionally limit results to a single feed or a custom date range.')]
class GetCalendarEvents extends Tool
{
    public function handle(Request $request, FetchCalendarEvents $fetchCalendarEvents): Response
    {
        $validated = $request->validate([
            'days' => ['sometimes', 'integer', 'min:1', 'max:30'],
            'from' => ['sometimes', 'date'],
            'feed_id' => ['sometimes', 'integer'],
        ], [
            'days.min' => 'The number of days must be at least 1.',
            'days.max' => 'The number of days may not be greater than 30.',
            'from.date' => 'The from argument must be a valid ISO date, for example "2026-07-08".',
        ]);

        $team = $request->user()->currentTeam;
        $timezone = $team->timezone ?: 'America/Chicago';

        $days = (int) ($validated['days'] ?? 7);
        $from = isset($validated['from'])
            ? CarbonImmutable::parse($validated['from'], $timezone)
            : CarbonImmutable::now($timezone)->startOfDay();

        $feeds = $team->calendarFeeds()->get();

        if (isset($validated['feed_id'])) {
            $feeds = $feeds->where('id', (int) $validated['feed_id'])->values();

            if ($feeds->isEmpty()) {
                return Response::error('Calendar feed not found.');
            }
        }

        if ($feeds->isEmpty()) {
            return Response::text('No calendar feeds have been added yet. Use the create-calendar-feed tool to add one.');
        }

        $warnings = collect();

        $events = $feeds
            ->flatMap(function (CalendarFeed $feed) use ($days, $from, $warnings, $fetchCalendarEvents) {
                $events = $fetchCalendarEvents->handle($feed, $days, $from);

                if ($feed->isFailing()) {
                    $warnings->push("Warning: could not fetch events from \"{$feed->name}\": {$feed->last_error}");
                }

                return $events;
            })
            ->sortBy('starts_at')
            ->values();

        return Response::text($this->format($events, $warnings, $from, $days, $timezone));
    }

    /** @return array<string, JsonSchema> */
    public function schema(JsonSchema $schema): array
    {
        return [
            'days' => $schema->integer()
                ->min(1)
                ->max(30)
                ->default(7)
                ->description('Number of days of events to fetch, starting from the "from" date. Defaults to 7.'),
            'from' => $schema->string()
                ->description('ISO date to start fetching events from, for example "2026-07-08". Defaults to today in the team\'s timezone.'),
            'feed_id' => $schema->integer()
                ->description('Limit results to a single calendar feed by its ID. Use the list-calendar-feeds tool to find feed IDs.'),
        ];
    }

    /**
     * @param  Collection<int, array{feed_name: string, title: string, location: string|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, all_day: bool}>  $events
     * @param  Collection<int, string>  $warnings
     */
    private function format(Collection $events, Collection $warnings, CarbonImmutable $from, int $days, string $timezone): string
    {
        $lines = collect([
            sprintf('Events for %d day(s) starting %s (%s):', $days, $from->toDateString(), $timezone),
        ]);

        if ($events->isEmpty()) {
            $lines->push('', 'No events found in this period.');
        }

        $events
            ->groupBy(fn (array $event): string => $event['starts_at']->toDateString())
            ->each(function (Collection $dayEvents, string $date) use ($lines): void {
                $lines->push('', '## ' . CarbonImmutable::parse($date)->format('l, F j, Y'));

                $dayEvents->each(fn (array $event) => $lines->push($this->formatEvent($event)));
            });

        if ($warnings->isNotEmpty()) {
            $lines->push('', ...$warnings->all());
        }

        return $lines->implode("\n");
    }

    /** @param  array{feed_name: string, title: string, location: string|null, starts_at: CarbonImmutable, ends_at: CarbonImmutable|null, all_day: bool}  $event */
    private function formatEvent(array $event): string
    {
        $time = $event['all_day']
            ? 'All day'
            : $event['starts_at']->format('g:i A') . ($event['ends_at'] ? ' - ' . $event['ends_at']->format('g:i A') : '');

        $line = "- {$time}: {$event['title']} [{$event['feed_name']}]";

        if ($event['location'] !== null) {
            $line .= " ({$event['location']})";
        }

        return $line;
    }
}
