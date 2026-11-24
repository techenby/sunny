<?php

declare(strict_types=1);

namespace App\Actions\Calendars;

use App\Models\CalendarFeed;
use App\Support\PublicHttp;
use Sabre\VObject\Reader;
use Throwable;

class RefreshCalendarFeed
{
    public function handle(CalendarFeed $feed): ?string
    {
        try {
            $ics = resolve(PublicHttp::class)->get($feed->url, [
                'Accept' => 'text/calendar,text/plain,*/*',
                'User-Agent' => 'SunnyCalendar/1.0',
            ])->throw()->body();

            Reader::read($ics);
        } catch (Throwable $exception) {
            $feed->fetched($exception);

            return null;
        }

        $feed->storeIcs($ics);
        $feed->fetched();

        return $ics;
    }
}
