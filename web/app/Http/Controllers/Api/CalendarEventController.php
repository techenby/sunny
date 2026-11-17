<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Actions\Calendars\FetchCalendarEvents;
use App\Http\Controllers\Controller;
use App\Http\Resources\CalendarEventResource;
use App\Models\CalendarFeed;
use App\Models\Team;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Gate;

class CalendarEventController extends Controller
{
    public function index(Request $request, Team $team, FetchCalendarEvents $fetchCalendarEvents): AnonymousResourceCollection
    {
        Gate::authorize('viewAny', CalendarFeed::class);

        $request->validate([
            'from' => ['nullable', 'date_format:Y-m-d'],
            'days' => ['nullable', 'integer', 'min:1', 'max:42'],
        ]);

        $from = $request->filled('from')
            ? CarbonImmutable::parse($request->input('from'), $team->timezone)
            : $team->today();
        $days = $request->integer('days', 7);

        $feeds = $team->calendarFeeds()->orderBy('name')->get();
        $events = $feeds
            ->flatMap(fn (CalendarFeed $feed) => $fetchCalendarEvents->handle($feed, $days, $from))
            ->sortBy('starts_at')
            ->values();

        return CalendarEventResource::collection($events)->additional([
            'from' => $from->toDateString(),
            'days' => $days,
            'timezone' => $team->timezone,
            'warnings' => $feeds
                ->filter(fn (CalendarFeed $feed): bool => $feed->isFailing())
                ->map(fn (CalendarFeed $feed): array => [
                    'feed_id' => $feed->id,
                    'feed_name' => $feed->name,
                    'message' => $feed->last_error,
                ])
                ->values(),
        ]);
    }
}
