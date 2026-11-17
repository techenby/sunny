<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Actions\Calendars\RefreshCalendarFeed;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Support\ClientUuid;

class CreateFeed
{
    /** @param  array<string, mixed>  $data */
    public function handle(Team $team, array $data): CalendarFeed
    {
        return ClientUuid::firstOrCreate(
            $team->calendarFeeds(),
            $data['client_uuid'] ?? null,
            function () use ($team, $data): CalendarFeed {
                $feed = $team->calendarFeeds()->create($data);

                (new RefreshCalendarFeed)->handle($feed);

                return $feed;
            },
        );
    }
}
