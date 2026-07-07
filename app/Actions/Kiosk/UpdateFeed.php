<?php

declare(strict_types=1);

namespace App\Actions\Kiosk;

use App\Models\CalendarFeed;

class UpdateFeed
{
    /** @param  array<string, mixed>  $data */
    public function handle(CalendarFeed $feed, array $data): CalendarFeed
    {
        if (isset($data['url']) && $data['url'] !== $feed->url) {
            $feed->fetched();
        }

        $feed->update($data);

        return $feed;
    }
}
