<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Enums\TeamRole;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

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

    $events = resolve(FetchCalendarEvents::class)->parse(
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

test('it records a failure and returns no events when the feed cannot be fetched', function () {
    Http::fake([
        'https://example.com/broken.ics' => Http::response('<!DOCTYPE html>Unauthorized', 401),
    ]);

    $feed = CalendarFeed::factory()->create(['url' => 'https://example.com/broken.ics']);

    $events = resolve(FetchCalendarEvents::class)->handle($feed);

    $feed->refresh();

    expect($events)->toBeEmpty()
        ->and($feed->isFailing())->toBeTrue()
        ->and($feed->last_error)->toBe('The calendar server responded with HTTP 401.');
});

test('it caches failures so a broken feed is not fetched on every render', function () {
    Http::fake([
        'https://example.com/broken.ics' => Http::response('', 401),
    ]);

    $feed = CalendarFeed::factory()->create(['url' => 'https://example.com/broken.ics']);

    resolve(FetchCalendarEvents::class)->handle($feed);
    resolve(FetchCalendarEvents::class)->handle($feed);

    Http::assertSentCount(1);
});

test('it clears the failure state after a successful fetch', function () {
    Http::fake([
        'https://example.com/fixed.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Calendar Test//EN
BEGIN:VEVENT
UID:crew-meeting@example.com
DTSTAMP:20260501T120000Z
DTSTART:20260505T150000Z
DTEND:20260505T160000Z
SUMMARY:Crew Meeting
END:VEVENT
END:VCALENDAR
ICS),
    ]);

    $feed = CalendarFeed::factory()->failing()->create(['url' => 'https://example.com/fixed.ics']);

    resolve(FetchCalendarEvents::class)->handle($feed);

    $feed->refresh();

    expect($feed->isFailing())->toBeFalse()
        ->and($feed->last_error)->toBeNull()
        ->and($feed->last_fetched_at)->not->toBeNull();
});
