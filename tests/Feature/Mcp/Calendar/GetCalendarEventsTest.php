<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Calendar\GetCalendarEvents;
use App\Models\CalendarFeed;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-05 08:00', 'America/Chicago'));
});

test('it returns upcoming events grouped by date', function () {
    Http::fake([
        'https://example.com/crew.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics'))),
    ]);

    $user = User::factory()->create();
    CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
    ]);

    SunnyServer::actingAs($user)
        ->tool(GetCalendarEvents::class)
        ->assertOk()
        ->assertSee('Tuesday, May 5, 2026')
        ->assertSee('10:00 AM - 11:00 AM: Crew Meeting [Crew Calendar] (The Galley)')
        ->assertSee('Wednesday, May 6, 2026')
        ->assertSee('All day: Shore Leave [Crew Calendar]');
});

test('it limits events to a single feed', function () {
    Http::fake([
        'https://example.com/crew.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics'))),
        'https://example.com/other.ics' => Http::response(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Sunny//Calendar Test//EN
        BEGIN:VEVENT
        UID:log-pose@example.com
        DTSTAMP:20260501T120000Z
        DTSTART:20260505T180000Z
        DTEND:20260505T190000Z
        SUMMARY:Log Pose Calibration
        END:VEVENT
        END:VCALENDAR
        ICS),
    ]);

    $user = User::factory()->create();
    $crew = CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
    ]);
    CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Navigation',
        'url' => 'https://example.com/other.ics',
    ]);

    SunnyServer::actingAs($user)
        ->tool(GetCalendarEvents::class, ['feed_id' => $crew->id])
        ->assertOk()
        ->assertSee('Crew Meeting')
        ->assertDontSee('Log Pose Calibration');
});

test('it rejects a calendar feed belonging to another team', function () {
    $user = User::factory()->create();
    $otherTeamFeed = CalendarFeed::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetCalendarEvents::class, ['feed_id' => $otherTeamFeed->id])
        ->assertHasErrors(['Calendar feed not found.']);
});

test('it validates the number of days', function () {
    $user = User::factory()->create();
    CalendarFeed::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(GetCalendarEvents::class, ['days' => 50])
        ->assertHasErrors(['The number of days may not be greater than 30.']);
});

test('it warns about a failing feed and still returns events from other feeds', function () {
    Http::fake([
        'https://example.com/crew.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics'))),
        'https://example.com/broken.ics' => Http::response('Unauthorized', 401),
    ]);

    $user = User::factory()->create();
    CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
    ]);
    CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Broken Calendar',
        'url' => 'https://example.com/broken.ics',
    ]);

    SunnyServer::actingAs($user)
        ->tool(GetCalendarEvents::class)
        ->assertOk()
        ->assertSee('Crew Meeting')
        ->assertSee('Warning: could not fetch events from "Broken Calendar": The calendar server responded with HTTP 401.');
});
