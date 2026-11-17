<?php

use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Http::preventStrayRequests();
    Storage::fake();
    $this->travelTo(CarbonImmutable::parse('2026-05-05 08:00', 'America/Chicago'));
});

test('guests cannot access calendar events', function () {
    $this->getJson(route('api.calendar-events.index', 'household'))->assertUnauthorized();
});

test('index returns upcoming events from every feed sorted by start time', function () {
    Http::fake([
        'https://example.com/crew.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics'))),
        'https://example.com/navigation.ics' => Http::response(<<<'ICS'
        BEGIN:VCALENDAR
        VERSION:2.0
        PRODID:-//Sunny//Calendar Test//EN
        BEGIN:VEVENT
        UID:log-pose@example.com
        DTSTAMP:20260501T120000Z
        DTSTART:20260505T130000Z
        DTEND:20260505T140000Z
        SUMMARY:Log Pose Calibration
        END:VEVENT
        END:VCALENDAR
        ICS),
    ]);

    $user = User::factory()->create();
    $crew = CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
        'color' => CalendarColor::Green,
    ]);
    CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Navigation',
        'url' => 'https://example.com/navigation.ics',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.calendar-events.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonPath('from', '2026-05-05')
        ->assertJsonPath('days', 7)
        ->assertJsonPath('timezone', 'America/Chicago')
        ->assertJsonPath('warnings', [])
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.title', 'Log Pose Calibration')
        ->assertJsonPath('data.1', [
            'feed_id' => $crew->id,
            'feed_name' => 'Crew Calendar',
            'feed_color' => CalendarColor::Green->value,
            'title' => 'Crew Meeting',
            'location' => 'The Galley',
            'date' => '2026-05-05',
            'all_day' => false,
            'starts_at' => '2026-05-05T10:00:00-05:00',
            'ends_at' => '2026-05-05T11:00:00-05:00',
            'response_status' => null,
        ])
        ->assertJsonPath('data.2.title', 'Shore Leave')
        ->assertJsonPath('data.2.date', '2026-05-06')
        ->assertJsonPath('data.2.all_day', true);
});

test('index limits events to the requested range', function () {
    Http::fake([
        'https://example.com/crew.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics'))),
    ]);

    $user = User::factory()->create();
    CalendarFeed::factory()->for($user->currentTeam)->create(['url' => 'https://example.com/crew.ics']);

    $this->actingAs($user)
        ->getJson(route('api.calendar-events.index', [$user->currentTeam, 'from' => '2026-05-06', 'days' => 1]))
        ->assertOk()
        ->assertJsonPath('from', '2026-05-06')
        ->assertJsonPath('days', 1)
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.title', 'Shore Leave');
});

test('index warns about a failing feed and still returns events from the others', function () {
    Http::fake([
        'https://example.com/crew.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics'))),
        'https://example.com/broken.ics' => Http::response('Unauthorized', 401),
    ]);

    $user = User::factory()->create();
    CalendarFeed::factory()->for($user->currentTeam)->create(['url' => 'https://example.com/crew.ics']);
    $broken = CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Broken Calendar',
        'url' => 'https://example.com/broken.ics',
    ]);

    $this->actingAs($user)
        ->getJson(route('api.calendar-events.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('warnings', [[
            'feed_id' => $broken->id,
            'feed_name' => 'Broken Calendar',
            'message' => 'The calendar server responded with HTTP 401.',
        ]]);

    expect($broken->fresh()->isFailing())->toBeTrue();
});

test('index returns no events when the team has no feeds', function () {
    Http::fake();
    $user = User::factory()->create();
    CalendarFeed::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.calendar-events.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('warnings', []);

    Http::assertNothingSent();
});

test('index validates the range', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.calendar-events.index', [$user->currentTeam, 'from' => 'next tuesday', 'days' => 43]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['from', 'days']);
});

test('index returns 403 for a team the user does not belong to', function () {
    Http::fake();
    $user = User::factory()->create();
    $team = Team::factory()->create();
    CalendarFeed::factory()->for($team)->create();

    $this->actingAs($user)
        ->getJson(route('api.calendar-events.index', $team))
        ->assertForbidden();

    Http::assertNothingSent();
});
