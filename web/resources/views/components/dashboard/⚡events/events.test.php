<?php

use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

test('it prompts to add a calendar when the team has no feeds', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('dashboard.events')
        ->assertSee(__('Add a calendar'));
});

test('it groups the next three days of events', function () {
    Date::setTestNow('2026-05-08 08:00:00');

    $team = Team::factory()->create(['timezone' => 'America/Chicago']);
    $user = User::factory()->memberOf($team)->create();
    CalendarFeed::factory()->for($team)->create(['url' => 'https://example.com/family.ics']);

    Http::fake([
        'https://example.com/family.ics' => Http::response(<<<'ICS'
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
BEGIN:VEVENT
UID:dentist
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260508T090000
DTEND;TZID=America/Chicago:20260508T100000
SUMMARY:Dentist
LOCATION:Main St
END:VEVENT
BEGIN:VEVENT
UID:soccer
DTSTAMP:20260501T120000Z
DTSTART;VALUE=DATE:20260510
DTEND;VALUE=DATE:20260511
SUMMARY:Soccer tournament
END:VEVENT
BEGIN:VEVENT
UID:later
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:20260512T090000
DTEND;TZID=America/Chicago:20260512T100000
SUMMARY:Next week
END:VEVENT
END:VCALENDAR
ICS),
    ]);

    $component = Livewire::actingAs($user)->test('dashboard.events');
    $days = $component->get('days');

    expect(collect($days)->pluck('label')->all())->toBe([__('Today'), __('Tomorrow'), 'Sunday'])
        ->and(collect($days[0]['events'])->pluck('title')->all())->toBe(['Dentist'])
        ->and($days[1]['events'])->toBe([])
        ->and(collect($days[2]['events'])->pluck('title')->all())->toBe(['Soccer tournament']);

    $component
        ->assertSee('9:00 - 10:00 AM')
        ->assertSee('Main St')
        ->assertSee(__('All day'))
        ->assertSee(__('Nothing scheduled.'))
        ->assertDontSee('Next week');
});

test('it does not show another team\'s feeds', function () {
    $user = User::factory()->create();
    CalendarFeed::factory()->create();

    Http::fake();

    Livewire::actingAs($user)
        ->test('dashboard.events')
        ->assertSee(__('Add a calendar'));

    Http::assertNothingSent();
});
