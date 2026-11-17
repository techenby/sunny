<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Actions\Calendars\RefreshCalendarFeed;
use App\Models\CalendarFeed;
use App\Models\Team;
use Illuminate\Support\Arr;

class CreateFeed
{
    /** @param  array<string, mixed>  $data */
    public function handle(Team $team, array $data): CalendarFeed
    {
        $feed = filled($data['client_uuid'] ?? null)
            ? $team->calendarFeeds()->firstOrCreate(Arr::only($data, 'client_uuid'), $data)
            : $team->calendarFeeds()->create($data);

        if ($feed->wasRecentlyCreated) {
            (new RefreshCalendarFeed)->handle($feed);
        }

        return $feed;
    }
}
