<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Models\CalendarFeed;
use App\Models\Team;

class CreateFeed
{
    /** @param  array<string, mixed>  $data */
    public function handle(Team $team, array $data): CalendarFeed
    {
        return $team->calendarFeeds()->create($data);
    }
}
