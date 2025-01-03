<?php

namespace App\Console\Commands;

use App\Actions\GetEventsFromLink;
use App\Models\Tile;
use Illuminate\Console\Command;

class FetchCalendarEvents extends Command
{
    /** @var string */
    protected $signature = 'app:fetch-calendar-events';

    /** @var string */
    protected $description = 'Fetch events from calendars defined in dashboard config';

    public function handle(): void
    {
        foreach (Tile::where('type', 'calendar')->get() as $tile) {
            $tile->data = collect($tile->settings['links'])->flatMap(function ($link) {
                if (! $link) {
                    return;
                }

                return (new GetEventsFromLink)($link, now()->subWeeks(2), now()->addWeeks(52))
                    ->sortBy('start');
            });

            $tile->save();
        }
    }
}
