<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Tile;
use Illuminate\Support\Carbon;

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
                        'allDay' => $allDay = str($event['duration'])->contains(['d', 'w']),
                        ...$allDay ? [
                            'start' => Carbon::parse($event['start'])->format('Y-m-d'),
                            'end' => Carbon::parse($event['end'])->format('Y-m-d'),
                        ] : [
                            'start' => $event['start'],
                            'end' => $event['end'],
                        ],
                        'backgroundColor' => $tile->settings['color'],
                        'borderColor' => $tile->settings['color'],
                    ]
                );
            })
            ->flatten(1);
    }
}
