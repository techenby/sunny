<?php

declare(strict_types=1);

namespace App\Actions\Calendars;

use App\Models\CalendarFeed;
use Illuminate\Support\Facades\Http;
use Sabre\VObject\Reader;
use Throwable;

class RefreshCalendarFeed
{
    public function handle(CalendarFeed $feed): ?string
    {
        try {
            $ics = Http::withHeaders([
                'Accept' => 'text/calendar,text/plain,*/*',
                'User-Agent' => 'SunnyCalendar/1.0',
            ])->timeout(10)->get($feed->url)->throw()->body();

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
