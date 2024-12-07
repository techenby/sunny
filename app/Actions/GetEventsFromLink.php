<?php

namespace App\Actions;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Sabre\VObject\Reader;

class GetEventsFromLink
{
    public function __invoke($link, $start = null, $end = null)
    {
        // documentation has `fopen($link, 'r')` but using Http::get makes it easier to test
        $content = Http::get($link)->body();
        $vcalendar = Reader::read($content);

        if ($start && $end) {
            $vcalendar = $vcalendar->expand($start, $end);
        }

        return collect($vcalendar->VEVENT)
            ->map(function ($event) {
                return [
                    'name' => (string) $event->SUMMARY,
                    'allDay' => $allDay = $event->DTSTART->getValueType() === 'DATE',
                    ...$allDay ? [
                        'start' => Carbon::parse($event->DTSTART?->getDateTime())->toDateString(),
                        'end' => $end = Carbon::parse($event->DTEND?->getDateTime())->toDateString(),
                    ] : [
                        'start' => Carbon::parse($event->DTSTART?->getDateTime())->timezone('America/Chicago'),
                        'end' => $end = Carbon::parse($event->DTEND?->getDateTime())->timezone('America/Chicago'),
                    ],
                    'past' => now()->isAfter($end),
                ];
            });
    }
}
