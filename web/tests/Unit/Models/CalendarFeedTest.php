<?php

use App\Models\CalendarFeed;
use Illuminate\Support\Facades\Storage;

test('deleting a feed drops its stored copy', function () {
    Storage::fake();

    $feed = CalendarFeed::factory()->withIcs('BEGIN:VCALENDAR' . "\r\n" . 'END:VCALENDAR')->create();
    $other = CalendarFeed::factory()->withIcs('BEGIN:VCALENDAR' . "\r\n" . 'END:VCALENDAR')->create();

    $feed->delete();

    Storage::assertMissing($feed->icsPath());
    Storage::assertExists($other->icsPath());
});
