<?php

namespace App\Actions;

use Illuminate\Support\Carbon;
use Sabre\VObject\Reader;

class GetEventsFromLink
{
    public function __invoke($link)
    {
        $vcalendar = Reader::read(fopen($link, 'r'));

        return collect($vcalendar->VEVENT)
            ->map(function ($event) {
                return [
                    'name' => (string) $event->SUMMARY,
                    'start' => $start = Carbon::parse($event->DTSTART?->getDateTimes()[0]),
                    'end' => $end = Carbon::parse($event->DTEND?->getDateTimes()[0]),
                    'duration' => $start->shortAbsoluteDiffForHumans($end),
                    'formatted' => $start->format('D, M jS g:i a'),
                ];
            });
    }
}
