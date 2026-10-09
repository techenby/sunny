<?php

use App\Enums\CalendarColor;
use App\Http\Integrations\OpenWeather\Requests\OneCall;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Saloon;

$calendar = fn (string $events): string => <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
{$events}
END:VCALENDAR
ICS;

$event = fn (string $uid, string $starts, string $summary, string $extra = ''): string => <<<ICS
BEGIN:VEVENT
UID:{$uid}
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:{$starts}
SUMMARY:{$summary}
{$extra}
END:VEVENT
ICS;

test('renders nothing without weather or events', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('kiosk.screensaver-details')
        ->assertOk()
        ->assertDontSee('data-screensaver-weather', escape: false)
        ->assertDontSee('data-screensaver-next-event', escape: false);
});

test('shows the current weather', function () {
    Saloon::fake([
        OneCall::class => MockResponse::make([
            'current' => [
                'temp' => 63.4,
                'weather' => [['description' => 'overcast clouds', 'icon' => '04d']],
            ],
            'daily' => [
                ['temp' => ['max' => 73.2, 'min' => 55.1]],
            ],
        ]),
    ]);

    $team = Team::factory()->create([
        'address' => ['city' => 'Chicago', 'lat' => '41.8781', 'long' => '-87.6298'],
    ]);
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('kiosk.screensaver-details')
        ->assertSee('63°')
        ->assertSee('overcast clouds')
        ->assertSee('73°')
        ->assertSee('55°');
});

test('shows the next event that has not started yet', function () use ($calendar, $event) {
    Date::setTestNow('2026-05-08 14:00:00 America/Chicago');

    $team = Team::factory()->create(['timezone' => 'America/Chicago']);
    $user = User::factory()->memberOf($team)->create();
    CalendarFeed::factory()->for($team)->create(['url' => 'https://example.com/family.ics', 'color' => CalendarColor::Green]);

    Http::fake([
        'https://example.com/family.ics' => Http::response($calendar(implode("\n", [
            $event('earlier', '20260508T090000', 'Dentist'),
            $event('soccer', '20260508T163000', 'Soccer practice'),
            $event('later', '20260508T190000', 'Dinner'),
        ]))),
    ]);

    $component = Livewire::actingAs($user)->test('kiosk.screensaver-details');

    expect($component->get('nextEvent'))
        ->title->toBe('Soccer practice')
        ->color->toBe(CalendarColor::Green->value)
        ->label->toBe(__('Next up'))
        ->time->toBe('4:30 PM');

    $component->assertSee('Soccer practice')->assertDontSee('Dentist')->assertDontSee('Dinner');
});

test('looks ahead to tomorrow and skips all-day and declined events', function () use ($calendar, $event) {
    Date::setTestNow('2026-05-08 21:00:00 America/Chicago');

    $team = Team::factory()->create(['timezone' => 'America/Chicago']);
    $user = User::factory()->memberOf($team)->create(['email' => 'luffy@example.com']);
    CalendarFeed::factory()->for($team)->create(['url' => 'https://example.com/family.ics']);

    Http::fake([
        'https://example.com/family.ics' => Http::response($calendar(implode("\n", [
            "BEGIN:VEVENT\nUID:holiday\nDTSTAMP:20260501T120000Z\nDTSTART;VALUE=DATE:20260509\nDTEND;VALUE=DATE:20260510\nSUMMARY:Holiday\nEND:VEVENT",
            $event('declined', '20260509T070000', 'Early meeting', 'ATTENDEE;PARTSTAT=DECLINED:mailto:luffy@example.com'),
            $event('breakfast', '20260509T081500', 'Breakfast'),
        ]))),
    ]);

    expect(Livewire::actingAs($user)->test('kiosk.screensaver-details')->get('nextEvent'))
        ->title->toBe('Breakfast')
        ->label->toBe(__('Tomorrow'))
        ->time->toBe('8:15 AM');
});

test('ignores events past tomorrow', function () use ($calendar, $event) {
    Date::setTestNow('2026-05-08 21:00:00 America/Chicago');

    $team = Team::factory()->create(['timezone' => 'America/Chicago']);
    $user = User::factory()->memberOf($team)->create();
    CalendarFeed::factory()->for($team)->create(['url' => 'https://example.com/family.ics']);

    Http::fake([
        'https://example.com/family.ics' => Http::response($calendar($event('weekend', '20260510T100000', 'Brunch'))),
    ]);

    expect(Livewire::actingAs($user)->test('kiosk.screensaver-details')->get('nextEvent'))->toBeNull();
});
