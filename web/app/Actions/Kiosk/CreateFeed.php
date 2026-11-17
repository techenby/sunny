<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Models\CalendarFeed;
use App\Models\Team;
use Illuminate\Support\Arr;

class CreateFeed
{
    /** @param  array<string, mixed>  $data */
    public function handle(Team $team, array $data): CalendarFeed
    {
        if (filled($data['client_uuid'] ?? null)) {
            return $team->calendarFeeds()->firstOrCreate(Arr::only($data, 'client_uuid'), $data);
        }

        return $team->calendarFeeds()->create($data);
    }
}
