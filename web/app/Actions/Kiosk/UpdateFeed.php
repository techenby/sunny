<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Actions\Calendars\RefreshCalendarFeed;
use App\Models\CalendarFeed;

class UpdateFeed
{
    /** @param  array<string, mixed>  $data */
    public function handle(CalendarFeed $feed, array $data): CalendarFeed
    {
        $urlChanged = isset($data['url']) && $data['url'] !== $feed->url;

        $feed->update($data);

        if ($urlChanged) {
            $feed->forgetIcs();

            (new RefreshCalendarFeed)->handle($feed);
        }

        return $feed;
    }
}
