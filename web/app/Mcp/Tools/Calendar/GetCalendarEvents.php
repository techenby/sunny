<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Calendar;

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tool;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[IsReadOnly]
#[IsOpenWorld]
#[Description('Get upcoming events from the team\'s calendar feeds, sorted by start time. Events include the start and end time (or an all-day flag), title, calendar name, and location when available. Optionally limit results to a single feed or a custom date range.')]
class GetCalendarEvents extends Tool
{
    public function handle(Request $request, FetchCalendarEvents $fetchCalendarEvents): Response|ResponseFactory
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
        $timezone = $team->timezone;

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

        $period = [
            'from' => $from->toDateString(),
            'days' => $days,
            'timezone' => $timezone,
        ];

        if ($feeds->isEmpty()) {
            return Response::structured([
                ...$period,
                'events' => [],
                'warnings' => [],
                'note' => 'No calendar feeds have been added yet. Use the create-calendar-feed tool to add one.',
            ]);
        }

        $warnings = collect();

        $events = $feeds
            ->flatMap(function (CalendarFeed $feed) use ($days, $from, $warnings, $fetchCalendarEvents) {
                $events = $fetchCalendarEvents->handle($feed, $days, $from);

                if ($feed->isFailing()) {
                    $warnings->push("Could not fetch events from \"{$feed->name}\": {$feed->last_error}");
                }

                return $events;
            })
            ->sortBy('starts_at')
            ->values();

        return Response::structured([
            ...$period,
            'events' => $events->map(fn (array $event): array => [
                'date' => $event['starts_at']->toDateString(),
                'title' => $event['title'],
                'feed_name' => $event['feed_name'],
                'location' => $event['location'],
                'all_day' => $event['all_day'],
                'starts_at' => $event['starts_at']->toIso8601String(),
                'ends_at' => $event['ends_at']?->toIso8601String(),
            ])->all(),
            'warnings' => $warnings->all(),
        ]);
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

    /** @return array<string, JsonSchema> */
    public function outputSchema(JsonSchema $schema): array
    {
        return [
            'from' => $schema->string()->required()
                ->description('The first date covered, as an ISO date.'),
            'days' => $schema->integer()->required(),
            'timezone' => $schema->string()->required()
                ->description("The team's timezone, which all event times are expressed in."),
            'events' => $schema->array()->items($schema->object([
                'date' => $schema->string()->required()
                    ->description('The ISO date the event starts on.'),
                'title' => $schema->string()->required(),
                'feed_name' => $schema->string()->required(),
                'location' => $schema->string()->nullable()->required(),
                'all_day' => $schema->boolean()->required(),
                'starts_at' => $schema->string()->required(),
                'ends_at' => $schema->string()->nullable()->required(),
            ]))->required()
                ->description('Events sorted by start time.'),
            'warnings' => $schema->array()->items($schema->string())->required()
                ->description('Feeds that could not be fetched.'),
            'note' => $schema->string(),
        ];
    }
}
