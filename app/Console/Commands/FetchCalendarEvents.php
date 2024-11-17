<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Sabre\VObject\Reader;
use Spatie\Dashboard\Models\Tile;

class FetchCalendarEvents extends Command
{
    /** @var string */
    protected $signature = 'app:fetch-calendar-events';

    /** @var string */
    protected $description = 'Fetch events from calendars defined in dashboard config';

    public function handle(): void
    {
        foreach (config('dashboard.tiles.calendar') as $name => $links) {
            $events = collect($links)->flatMap(function ($link) {
                if (! $link) {
                    return;
                }

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
                    })
                    ->filter(fn ($event) => $event['end']->isFuture())
                    ->sortBy('start');
            });

            Tile::updateOrCreate(
                ['name' => "calendar-{$name}"],
                ['data' => $events]
            );
        }
    }
}
