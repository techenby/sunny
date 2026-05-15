<?php

use Illuminate\Support\Facades\Date;
use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
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
        ->assertSee('Cinco de Mayo');
});

test('can go to the next and previous weeks', function () {
    $this->travelTo(Date::parse('2026-05-08'));

    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.calendar')
        ->assertSeeInOrder([
            'calendar-day-2026-05-03', 'calendar-day-2026-05-04', 'calendar-day-2026-05-05', 'calendar-day-2026-05-06', 'calendar-day-2026-05-07', 'calendar-day-2026-05-08', 'calendar-day-2026-05-09',
        ])
        ->call('previousWeek')
        ->assertSeeInOrder([
            'calendar-day-2026-04-26', 'calendar-day-2026-04-27', 'calendar-day-2026-04-28', 'calendar-day-2026-04-29', 'calendar-day-2026-04-30', 'calendar-day-2026-05-01', 'calendar-day-2026-05-02',
        ])
        ->call('currentWeek')
        ->assertSeeInOrder([
            'calendar-day-2026-05-03', 'calendar-day-2026-05-04', 'calendar-day-2026-05-05', 'calendar-day-2026-05-06', 'calendar-day-2026-05-07', 'calendar-day-2026-05-08', 'calendar-day-2026-05-09',
        ])
        ->call('nextWeek')
        ->assertSeeInOrder([
            'calendar-day-2026-05-10', 'calendar-day-2026-05-11', 'calendar-day-2026-05-12', 'calendar-day-2026-05-13', 'calendar-day-2026-05-14', 'calendar-day-2026-05-15', 'calendar-day-2026-05-16',
        ]);
});

test('can hide feed from calendar', function () {
    Http::allowStrayRequests([
        'https://calendar.google.com/calendar/ical/*',
        'https://worldpublicholiday.com/calendar-feeds/*'
    ]);

    $this->travelTo(Date::parse('2026-03-20'));

    $team = Team::factory()
        ->has(
            CalendarFeed::factory()
                ->count(2)
                ->sequence([
                    'url' => 'https://calendar.google.com/calendar/ical/en.usa%23holiday%40group.v.calendar.google.com/public/basic.ics',
                    'name' => 'US Holidays',
                    'color' => CalendarColor::Green,
                ], [
                    'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=BR&year=2026',
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
            'calendar-day-2026-03-19', 'Dia de São José'
        ])
        ->set('selectedFeeds', [$usHolidays->id])
        ->assertSeeInOrder([
            'calendar-day-2026-03-17', "St. Patrick's Day",
            'calendar-day-2026-03-18', // 'Autonomia do Estado',
            'calendar-day-2026-03-19', // 'Dia de São José'
        ])
        ->assertDontSee(['Autonomia do Estado', 'Dia de São José']);
});
