<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;

it('renders successfully', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('dashboard.calendar')
        ->assertStatus(200);
});

it('adds calendar feeds for the authenticated user', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('dashboard.calendar')
        ->set('feedName', 'Work')
        ->set('feedUrl', 'https://calendar.example.com/basic.ics,')
        ->set('feedColor', '#16a34a')
        ->call('addFeed')
        ->assertSet('feedName', '')
        ->assertSet('feedUrl', '')
        ->assertSet('feedColor', '#2563eb');

    expect($user->calendarFeeds()->first())
        ->name->toBe('Work')
        ->url->toBe('https://calendar.example.com/basic.ics')
        ->color->toBe('#16a34a');
});

it('requires a valid calendar feed url', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('dashboard.calendar')
        ->set('feedUrl', 'not-a-url')
        ->call('addFeed')
        ->assertHasErrors(['feedUrl']);
});

it('requires a valid calendar feed color', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('dashboard.calendar')
        ->set('feedUrl', 'https://calendar.example.com/basic.ics')
        ->set('feedColor', '#ffffff')
        ->call('addFeed')
        ->assertHasErrors(['feedColor']);
});

it('updates only the authenticated users feed colors', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user)->create(['color' => '#2563eb']);
    $otherFeed = CalendarFeed::factory()->for($otherUser)->create(['color' => '#2563eb']);

    Livewire::actingAs($user)
        ->test('dashboard.calendar')
        ->call('updateFeedColor', $feed->id, '#dc2626')
        ->call('updateFeedColor', $otherFeed->id, '#dc2626');

    expect($feed->fresh()->color)->toBe('#dc2626')
        ->and($otherFeed->fresh()->color)->toBe('#2563eb');
});

it('removes only the authenticated users calendar feeds', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user)->create();
    $otherFeed = CalendarFeed::factory()->for($otherUser)->create();

    Livewire::actingAs($user)
        ->test('dashboard.calendar')
        ->call('deleteFeed', $feed->id)
        ->call('deleteFeed', $otherFeed->id);

    expect($feed->fresh())->toBeNull()
        ->and($otherFeed->fresh())->not->toBeNull();
});

it('shows upcoming events from calendar feeds', function () {
    CarbonImmutable::setTestNow('2026-05-14 09:00:00');
    Cache::store()->flush();
    Http::fake([
        'calendar.example.com/*' => Http::response(calendarFixture()),
    ]);

    $user = User::factory()->create();
    CalendarFeed::factory()->for($user)->create([
        'name' => 'Work',
        'url' => 'https://calendar.example.com/basic.ics',
        'color' => '#9333ea',
    ]);

    Livewire::actingAs($user)
        ->test('dashboard.calendar')
        ->assertSee('Design review')
        ->assertSee('Work')
        ->assertSee('#9333ea')
        ->assertSee('10:00 AM')
        ->assertSee('Office');

    CarbonImmutable::setTestNow();
});

it('shows events for the selected week and can navigate weeks', function () {
    CarbonImmutable::setTestNow('2026-05-14 09:00:00');
    Cache::store()->flush();
    Http::fake([
        'calendar.example.com/*' => Http::response(calendarFixture(<<<ICS
BEGIN:VEVENT
UID:this-week@example.com
SUMMARY:This week review
DTSTART:20260514T150000Z
DTEND:20260514T160000Z
END:VEVENT
BEGIN:VEVENT
UID:next-week@example.com
SUMMARY:Next week planning
DTSTART:20260520T150000Z
DTEND:20260520T160000Z
END:VEVENT
ICS)),
    ]);

    $user = User::factory()->create();
    CalendarFeed::factory()->for($user)->create([
        'name' => 'Work',
        'url' => 'https://calendar.example.com/basic.ics',
    ]);

    Livewire::actingAs($user)
        ->test('dashboard.calendar')
        ->assertSee('May 10 - 16, 2026')
        ->assertSee('This week review')
        ->assertDontSee('Next week planning')
        ->call('nextWeek')
        ->assertSee('May 17 - 23, 2026')
        ->assertSee('Next week planning')
        ->assertDontSee('This week review');

    CarbonImmutable::setTestNow();
});

it('expands simple recurring calendar events', function () {
    $feed = CalendarFeed::factory()->make([
        'id' => 10,
        'name' => 'Family',
    ]);

    $events = resolve(FetchCalendarEvents::class)->parse(
        calendarFixture(<<<ICS
BEGIN:VEVENT
UID:standup@example.com
SUMMARY:Standup
DTSTART:20260514T140000Z
DTEND:20260514T143000Z
RRULE:FREQ=WEEKLY;COUNT=3
END:VEVENT
ICS),
        $feed,
        CarbonImmutable::parse('2026-05-14 00:00:00'),
        30,
    );

    expect($events)->toHaveCount(3)
        ->and($events->pluck('title')->all())->toBe(['Standup', 'Standup', 'Standup']);
});

it('honors excluded recurring calendar dates', function () {
    $feed = CalendarFeed::factory()->make([
        'id' => 10,
        'name' => 'Family',
    ]);

    $events = resolve(FetchCalendarEvents::class)->parse(
        calendarFixture(<<<ICS
BEGIN:VEVENT
UID:standup@example.com
SUMMARY:Standup
DTSTART:20260514T140000Z
DTEND:20260514T143000Z
RRULE:FREQ=WEEKLY;COUNT=3
EXDATE:20260521T140000Z
END:VEVENT
ICS),
        $feed,
        CarbonImmutable::parse('2026-05-14 00:00:00'),
        30,
    );

    expect($events)->toHaveCount(2)
        ->and($events->pluck('starts_at')->map->toDateString()->all())->toBe(['2026-05-14', '2026-05-28']);
});

it('converts calendar feed times to the users timezone', function () {
    $user = User::factory()->create(['timezone' => 'America/New_York']);
    $feed = CalendarFeed::factory()->for($user)->create([
        'name' => 'Work',
        'url' => 'https://calendar.example.com/basic.ics',
    ]);

    $events = resolve(FetchCalendarEvents::class)->parse(
        calendarFixture(),
        $feed,
        CarbonImmutable::parse('2026-05-14 00:00:00', 'America/New_York'),
        30,
    );

    expect($events)->toHaveCount(1)
        ->and($events->first()['starts_at']->timezoneName)->toBe('America/New_York')
        ->and($events->first()['starts_at']->format('g:i A'))->toBe('11:00 AM');
});

function calendarFixture(string $events = ''): string
{
    $events = $events ?: <<<ICS
BEGIN:VEVENT
UID:design-review@example.com
SUMMARY:Design review
LOCATION:Office
DTSTART:20260514T150000Z
DTEND:20260514T160000Z
END:VEVENT
ICS;

    return <<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Calendar Test//EN
{$events}
END:VCALENDAR
ICS;
}
