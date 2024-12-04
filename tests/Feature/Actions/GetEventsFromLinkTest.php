<?php

use App\Actions\GetEventsFromLink;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->exampleCalendar = 'BEGIN:VCALENDAR
PRODID:-//Proton AG//ProtonCalendar 1.0.0//EN
VERSION:2.0
CALSCALE:GREGORIAN
METHOD:PUBLISH
X-WR-CALNAME:Routines
X-WR-CALDESC:
X-WR-TIMEZONE:America/Chicago
REFRESH-INTERVAL;VALUE=DURATION:PT240M
X-PUBLISHED-TTL:PT240M
BEGIN:VTIMEZONE
TZID:America/Chicago
LAST-MODIFIED:20240317T125125Z
X-LIC-LOCATION:America/Chicago
BEGIN:DAYLIGHT
TZNAME:CDT
TZOFFSETFROM:-0600
TZOFFSETTO:-0500
DTSTART:19700308T020000
RRULE:FREQ=YEARLY;BYMONTH=3;BYDAY=2SU
END:DAYLIGHT
BEGIN:STANDARD
TZNAME:CST
TZOFFSETFROM:-0500
TZOFFSETTO:-0600
DTSTART:19701101T020000
RRULE:FREQ=YEARLY;BYMONTH=11;BYDAY=1SU
END:STANDARD
END:VTIMEZONE
BEGIN:VEVENT
SUMMARY:Morning Routine
UID:12345A@proton.me
DTSTAMP:20241116T162635Z
DTSTART;TZID=America/Chicago:20241114T080000
DTEND;TZID=America/Chicago:20241114T083000
SEQUENCE:0
RRULE:FREQ=DAILY;WKST=SU
STATUS:CONFIRMED
END:VEVENT
END:VCALENDAR';
});

test('can get events from calendar', function () {
    Http::fake([
        '*' => Http::response($this->exampleCalendar, 200, ['Content-Type' => 'text/calendar; charset=utf-8']),
    ]);

    $events = (new GetEventsFromLink)('http://example.test');

    expect($events)->toHaveCount(1);
    expect($events->first())->toMatchArray([
        'name' => 'Morning Routine',
        'start' => Carbon::parse('2024-11-14 8:00', 'America/Chicago'),
        'end' => Carbon::parse('2024-11-14 8:30', 'America/Chicago'),
    ]);
});

test('can expand calendar for recurring events', function () {
    Http::fake([
        '*' => Http::response($this->exampleCalendar, 200, ['Content-Type' => 'text/calendar; charset=utf-8']),
    ]);

    $start = Carbon::parse('2024-12-01', 'UTC');
    $end = Carbon::parse('2024-12-03', 'UTC');
    $events = (new GetEventsFromLink)('http://example.test', $start, $end);

    expect($events)->toHaveCount(2);
    expect($events->first())->toMatchArray([
        'name' => 'Morning Routine',
        'start' => Carbon::parse('2024-12-01 8:00', 'America/Chicago'),
        'end' => Carbon::parse('2024-12-01 8:30', 'America/Chicago'),
    ]);
});
