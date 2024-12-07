<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tile;

class EventController extends Controller
{
    public function __invoke()
    {
        return Tile::where('type', 'calendar')->get()
            ->map(function ($tile) {
                return collect($tile->data)->map(
                    fn ($event) => [
                        'title' => $event['name'],
                        'calendar' => str($tile->name)
                            ->replace('calendar-', '')
                            ->ucfirst()
                            ->toString(),
                        'allDay' => $event['allDay'],
                        'start' => $event['start'],
                        'end' => $event['end'],
                        'backgroundColor' => $tile->settings['color'],
                        'borderColor' => $tile->settings['color'],
                    ]
                );
            })
            ->flatten(1);
    }
}
