<?php

use App\Enums\Appearance;
use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('kiosk.calendar'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->assertOk();
})->group('smoke');

test('kiosk layout follows the team appearance setting', function () {
    $team = Team::factory()->create(['appearance' => Appearance::Dark]);
    $user = User::factory()->memberOf($team)->create();

    actingAs($user)
        ->get(route('kiosk.calendar'))
        ->assertSee('class="dark"', false)
        ->assertSee("window.localStorage.setItem('flux.appearance', 'dark')", false);

    $team->update(['appearance' => Appearance::Light]);

    actingAs($user)
        ->get(route('kiosk.calendar'))
        ->assertDontSee('class="dark"', false)
        ->assertSee("window.localStorage.setItem('flux.appearance', 'light')", false);

    $team->update(['appearance' => Appearance::System]);

    actingAs($user)
        ->get(route('kiosk.calendar'))
        ->assertDontSee('class="dark"', false)
        ->assertSee("window.localStorage.setItem('flux.appearance', 'system')", false);
});

test('kiosk layout follows the team rotation setting', function () {
    $team = Team::factory()->create(['rotation' => 0]);
    $user = User::factory()->memberOf($team)->create();

    $html = actingAs($user)->get(route('kiosk.calendar'))->getContent();

    expect($html)->not->toMatch('/<body[^>]*data-rotation/');

    $team->update(['rotation' => 90]);

    $html = actingAs($user)->get(route('kiosk.calendar'))->getContent();

    expect($html)->toMatch('/<body[^>]*data-rotation="90"/');
});

test('can view events from feed', function () {
    Http::allowStrayRequests(['https://calendar.google.com/calendar/ical/*']);

    $this->travelTo(Date::parse('2026-05-08'));

    $team = Team::factory()
        ->has(CalendarFeed::factory()->state([
            'url' => 'https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics',
            'name' => 'US Holidays',
            'color' => CalendarColor::Green,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->assertSee('Cinco de Mayo')
        ->set('format', 'day')
        ->set('focusedDate', '2026-05-05')
        ->assertSee('Cinco de Mayo')
        ->set('format', 'month')
        ->assertSee('Cinco de Mayo');
});

test('can view and navigate a day calendar', function () {
    $this->travelTo(Date::parse('2026-05-08 12:00:00', 'America/Chicago'));

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->set('format', 'day')
        ->assertSee('Friday')
        ->assertSee('May 8')
        ->call('previous')
        ->assertSee('Thursday')
        ->assertSee('May 7')
        ->call('current')
        ->assertSee('Friday')
        ->assertSee('May 8')
        ->call('next')
        ->assertSee('Saturday')
        ->assertSee('May 9');
});

test('day calendar shows hours and sizes timed events by duration', function () {
    Http::fake([
        'https://example.com/day-calendar.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
BEGIN:VEVENT
UID:family-day
DTSTAMP:20260501T120000Z
DTSTART;VALUE=DATE:20260508
DTEND;VALUE=DATE:20260509
SUMMARY:Family Day
END:VEVENT
BEGIN:VEVENT
UID:morning-standup
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260508T090000
DTEND;TZID=America/Chicago:20260508T103000
SUMMARY:Morning Standup
LOCATION:Kitchen
END:VEVENT
BEGIN:VEVENT
UID:family-lunch
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260508T100000
DTEND;TZID=America/Chicago:20260508T130000
SUMMARY:Family Lunch
END:VEVENT
BEGIN:VEVENT
UID:evening-game
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260508T170000
DTEND;TZID=America/Chicago:20260508T190000
SUMMARY:Evening Game
END:VEVENT
END:VCALENDAR
ICS),
    ]);

    $this->travelTo(Date::parse('2026-05-08 12:00:00', 'America/Chicago'));

    $team = Team::factory()
        ->has(CalendarFeed::factory()->state([
            'url' => 'https://example.com/day-calendar.ics',
            'name' => 'Family Calendar',
            'color' => CalendarColor::Blue,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->set('format', 'day')
        ->assertSee('12 AM')
        ->assertSee('9 AM')
        ->assertSee('All day')
        ->assertSee('Family Day')
        ->assertSee('Morning Standup')
        ->assertSee('Family Lunch')
        ->assertSee('Evening Game')
        ->assertSee('Kitchen')
        ->assertSee('9:00 - 10:30 AM')
        ->assertSee('10:00 AM - 1:00 PM')
        ->assertSee('5:00 - 7:00 PM')
        ->assertSee('top: 37.5%', false)
        ->assertSee('height: 6.25%', false);
});

test('can go to the next and previous weeks', function () {
    $this->travelTo(Date::parse('2026-05-08'));

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->assertSeeInOrder([
            'calendar-day-2026-05-03', 'calendar-day-2026-05-04', 'calendar-day-2026-05-05', 'calendar-day-2026-05-06', 'calendar-day-2026-05-07', 'calendar-day-2026-05-08', 'calendar-day-2026-05-09',
        ])
        ->call('previous')
        ->assertSeeInOrder([
            'calendar-day-2026-04-26', 'calendar-day-2026-04-27', 'calendar-day-2026-04-28', 'calendar-day-2026-04-29', 'calendar-day-2026-04-30', 'calendar-day-2026-05-01', 'calendar-day-2026-05-02',
        ])
        ->call('current')
        ->assertSeeInOrder([
            'calendar-day-2026-05-03', 'calendar-day-2026-05-04', 'calendar-day-2026-05-05', 'calendar-day-2026-05-06', 'calendar-day-2026-05-07', 'calendar-day-2026-05-08', 'calendar-day-2026-05-09',
        ])
        ->call('next')
        ->assertSeeInOrder([
            'calendar-day-2026-05-10', 'calendar-day-2026-05-11', 'calendar-day-2026-05-12', 'calendar-day-2026-05-13', 'calendar-day-2026-05-14', 'calendar-day-2026-05-15', 'calendar-day-2026-05-16',
        ]);
});

test('can view and navigate a month calendar', function () {
    $this->travelTo(Date::parse('2026-05-15'));

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->set('format', 'month')
        ->assertSeeInOrder([
            'calendar-day-2026-04-26', 'calendar-day-2026-04-27', 'calendar-day-2026-04-28', 'calendar-day-2026-04-29', 'calendar-day-2026-04-30', 'calendar-day-2026-05-01', 'calendar-day-2026-05-02',
            'calendar-day-2026-05-03', 'calendar-day-2026-05-04', 'calendar-day-2026-05-05', 'calendar-day-2026-05-06', 'calendar-day-2026-05-07', 'calendar-day-2026-05-08', 'calendar-day-2026-05-09',
            'calendar-day-2026-05-31', 'calendar-day-2026-06-01', 'calendar-day-2026-06-02', 'calendar-day-2026-06-03', 'calendar-day-2026-06-04', 'calendar-day-2026-06-05', 'calendar-day-2026-06-06',
        ])
        ->call('previous')
        ->assertSeeInOrder([
            'calendar-day-2026-03-29', 'calendar-day-2026-03-30', 'calendar-day-2026-03-31', 'calendar-day-2026-04-01', 'calendar-day-2026-04-02', 'calendar-day-2026-04-03', 'calendar-day-2026-04-04',
        ])
        ->call('current')
        ->assertSeeInOrder([
            'calendar-day-2026-04-26', 'calendar-day-2026-04-27', 'calendar-day-2026-04-28', 'calendar-day-2026-04-29', 'calendar-day-2026-04-30', 'calendar-day-2026-05-01', 'calendar-day-2026-05-02',
        ])
        ->call('next')
        ->assertSeeInOrder([
            'calendar-day-2026-05-31', 'calendar-day-2026-06-01', 'calendar-day-2026-06-02', 'calendar-day-2026-06-03', 'calendar-day-2026-06-04', 'calendar-day-2026-06-05', 'calendar-day-2026-06-06',
        ]);
});

test('month calendar shows two events before overflow count', function () {
    Http::fake([
        'https://example.com/month-calendar.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
BEGIN:VEVENT
UID:first-event
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260515T090000
DTEND;TZID=America/Chicago:20260515T100000
SUMMARY:First Event
END:VEVENT
BEGIN:VEVENT
UID:second-event
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260515T110000
DTEND;TZID=America/Chicago:20260515T120000
SUMMARY:Second Event
END:VEVENT
BEGIN:VEVENT
UID:third-event
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260515T130000
DTEND;TZID=America/Chicago:20260515T140000
SUMMARY:Third Event
END:VEVENT
END:VCALENDAR
ICS),
    ]);

    $this->travelTo(Date::parse('2026-05-15 12:00:00', 'America/Chicago'));

    $team = Team::factory()
        ->has(CalendarFeed::factory()->state([
            'url' => 'https://example.com/month-calendar.ics',
            'name' => 'Family Calendar',
            'color' => CalendarColor::Blue,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->set('format', 'month')
        ->assertSee('First Event')
        ->assertSee('Second Event')
        ->assertSee('+1 events')
        ->assertDontSee('Third Event');
});

test('shows a warning banner when a feed fails to load', function () {
    Http::fake([
        'https://example.com/good.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
BEGIN:VEVENT
UID:morning-standup
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260508T090000
DTEND;TZID=America/Chicago:20260508T103000
SUMMARY:Morning Standup
END:VEVENT
END:VCALENDAR
ICS),
        'https://example.com/broken.ics' => Http::response('<!DOCTYPE html>Unauthorized', 401),
    ]);

    $this->travelTo(Date::parse('2026-05-08 12:00:00', 'America/Chicago'));

    $team = Team::factory()
        ->has(
            CalendarFeed::factory()
                ->count(2)
                ->sequence([
                    'url' => 'https://example.com/good.ics',
                    'name' => 'Family Calendar',
                    'color' => CalendarColor::Blue,
                ], [
                    'url' => 'https://example.com/broken.ics',
                    'name' => 'Work Calendar',
                    'color' => CalendarColor::Red,
                ])
        )
        ->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->assertSee("Couldn't load Work Calendar.")
        ->assertSee('Open Sunny on your phone or computer')
        ->assertSee('Morning Standup');

    expect($team->calendarFeeds()->firstWhere('name', 'Work Calendar')->isFailing())->toBeTrue()
        ->and($team->calendarFeeds()->firstWhere('name', 'Family Calendar')->isFailing())->toBeFalse();
});

test('does not show a warning banner when all feeds load', function () {
    Http::fake([
        'https://example.com/good.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
END:VCALENDAR
ICS),
    ]);

    $team = Team::factory()
        ->has(CalendarFeed::factory()->state([
            'url' => 'https://example.com/good.ics',
            'name' => 'Family Calendar',
            'color' => CalendarColor::Blue,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->assertDontSee("Couldn't load");
});

test('can hide feed from calendar', function () {
    Http::fake([
        'https://example.com/us.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
BEGIN:VEVENT
UID:st-patricks-day
DTSTAMP:20260301T120000Z
DTSTART;VALUE=DATE:20260317
DTEND;VALUE=DATE:20260318
SUMMARY:St. Patrick's Day
END:VEVENT
END:VCALENDAR
ICS),
        'https://example.com/br.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
BEGIN:VEVENT
UID:autonomia-do-estado
DTSTAMP:20260301T120000Z
DTSTART;VALUE=DATE:20260318
DTEND;VALUE=DATE:20260319
SUMMARY:Autonomia do Estado
END:VEVENT
BEGIN:VEVENT
UID:dia-de-sao-jose
DTSTAMP:20260301T120000Z
DTSTART;VALUE=DATE:20260319
DTEND;VALUE=DATE:20260320
SUMMARY:Dia de São José
END:VEVENT
END:VCALENDAR
ICS),
    ]);

    $this->travelTo(Date::parse('2026-03-20'));

    $team = Team::factory()
        ->has(
            CalendarFeed::factory()
                ->count(2)
                ->sequence([
                    'url' => 'https://example.com/us.ics',
                    'name' => 'US Holidays',
                    'color' => CalendarColor::Green,
                ], [
                    'url' => 'https://example.com/br.ics',
                    'name' => 'Brazilian Holidays',
                    'color' => CalendarColor::Blue,
                ])
        )
        ->create();
    $user = User::factory()->memberOf($team)->create();
    [$brazilianHolidays, $usHolidays] = $team->calendarFeeds;

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->assertSet('selectedFeeds', [$brazilianHolidays->id, $usHolidays->id])
        ->assertSeeInOrder([
            'calendar-day-2026-03-17', "St. Patrick's Day",
            'calendar-day-2026-03-18', 'Autonomia do Estado',
            'calendar-day-2026-03-19', 'Dia de São José',
        ])
        ->set('selectedFeeds', [$usHolidays->id])
        ->assertSeeInOrder([
            'calendar-day-2026-03-17', "St. Patrick's Day",
            'calendar-day-2026-03-18', // 'Autonomia do Estado',
            'calendar-day-2026-03-19', // 'Dia de São José'
        ])
        ->assertDontSee(['Autonomia do Estado', 'Dia de São José']);
});
