<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Enums\TeamRole;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

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

test('it does not retry a failed download on every render', function () {
    Http::fake([
        'https://example.com/broken.ics' => Http::response('', 401),
    ]);

    $feed = CalendarFeed::factory()->create(['url' => 'https://example.com/broken.ics']);

    resolve(FetchCalendarEvents::class)->handle($feed);
    resolve(FetchCalendarEvents::class)->handle($feed);

    Http::assertSentCount(1);
});

test('it downloads a new feed once and reads the stored copy after that', function () {
    Http::fake([
        'https://example.com/crew.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics'))),
    ]);

    $feed = CalendarFeed::factory()->create(['url' => 'https://example.com/crew.ics']);
    $from = CarbonImmutable::parse('2026-05-05', 'America/Chicago');

    $first = resolve(FetchCalendarEvents::class)->handle($feed, 2, $from);
    $second = resolve(FetchCalendarEvents::class)->handle($feed->fresh(), 2, $from);

    Http::assertSentCount(1);
    Storage::assertExists($feed->icsPath());

    expect($first->pluck('title')->all())->toBe(['Crew Meeting', 'Shore Leave'])
        ->and($second->pluck('title')->all())->toBe(['Crew Meeting', 'Shore Leave'])
        ->and($feed->fresh()->ics_fetched_at)->not->toBeNull();
});

test('it reads the stored copy without downloading the feed', function () {
    Http::fake();

    $feed = CalendarFeed::factory()
        ->withIcs(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))
        ->create(['url' => 'https://example.com/crew.ics']);

    $events = resolve(FetchCalendarEvents::class)->handle($feed, 2, CarbonImmutable::parse('2026-05-05', 'America/Chicago'));

    Http::assertNothingSent();

    expect($events->pluck('title')->all())->toBe(['Crew Meeting', 'Shore Leave']);
});

test('it shows events from the stored copy while the feed is failing', function () {
    Http::fake();

    $feed = CalendarFeed::factory()
        ->failing()
        ->withIcs(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))
        ->create(['url' => 'https://example.com/crew.ics']);

    $events = resolve(FetchCalendarEvents::class)->handle($feed, 2, CarbonImmutable::parse('2026-05-05', 'America/Chicago'));

    Http::assertNothingSent();

    expect($events->pluck('title')->all())->toBe(['Crew Meeting', 'Shore Leave'])
        ->and($feed->fresh()->isFailing())->toBeTrue();
});
