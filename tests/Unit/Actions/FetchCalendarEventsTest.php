<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Enums\TeamRole;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;

test('it checks attendee response status against team member emails', function () {
    $team = Team::factory()->create();
    $team->members()->attach(
        User::factory()->create(['email' => 'zoro@example.com']),
        ['role' => TeamRole::Member->value],
    );
    $team->members()->attach(
        User::factory()->create(['email' => 'nami@example.com']),
        ['role' => TeamRole::Member->value],
    );

    $feed = CalendarFeed::factory()->for($team)->create([
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/calendar.ics',
    ]);

    $events = app(FetchCalendarEvents::class)->parse(
        ics: <<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Calendar Test//EN
BEGIN:VEVENT
UID:crew-meeting@example.com
DTSTAMP:20260501T120000Z
DTSTART:20260505T150000Z
DTEND:20260505T160000Z
SUMMARY:Crew Meeting
ATTENDEE;PARTSTAT=ACCEPTED:mailto:zoro@example.com
ATTENDEE;PARTSTAT=DECLINED:mailto:sanji@example.com
END:VEVENT
END:VCALENDAR
ICS,
        feed: $feed,
        from: CarbonImmutable::parse('2026-05-05', 'America/Chicago'),
        days: 1,
    );

    expect($events)->toHaveCount(1)
        ->and($events->first()['response_status'])->toBe('ACCEPTED');
});
