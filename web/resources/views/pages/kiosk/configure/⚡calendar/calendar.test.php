<?php

use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $team = Team::factory()
        ->has(CalendarFeed::factory()->state([
            'name' => 'Family Calendar',
            'url' => 'https://example.com/family.ics',
            'color' => CalendarColor::Green,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    actingAs($user)
        ->get(route('kiosk.configure.calendar'))
        ->assertOk()
        ->assertSee('Calendar feeds that show up on the kiosk')
        ->assertSee('Family Calendar');

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->assertOk()
        ->assertSee('Family Calendar');
})->group('smoke');

test('can add a calendar feed', function () {
    Storage::fake();
    Http::fake(['https://worldpublicholiday.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))]);

    $team = Team::factory()->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->set('form.name', 'Brazilian Holidays')
        ->set('form.url', 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=BR&year=2026')
        ->set('form.color', CalendarColor::Green->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.name', '')
        ->assertSet('form.url', '')
        ->assertSet('form.color', CalendarColor::Blue->value);

    $this->assertDatabaseHas('calendar_feeds', [
        'team_id' => $team->id,
        'name' => 'Brazilian Holidays',
        'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=BR&year=2026',
        'color' => CalendarColor::Green->value,
    ]);
});

test('can edit a team calendar feed', function () {
    Storage::fake();
    Http::fake(['https://example.com/american.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))]);

    $team = Team::factory()
        ->has(CalendarFeed::factory()->state([
            'name' => 'US Holidays',
            'url' => 'https://example.com/us.ics',
            'color' => CalendarColor::Red,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();
    $feed = $team->calendarFeeds()->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->call('edit', $feed->id)
        ->assertSet('form.name', 'US Holidays')
        ->set('form.name', 'American Holidays')
        ->set('form.url', 'https://example.com/american.ics')
        ->set('form.color', CalendarColor::Indigo->value)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.editingFeed', null);

    $this->assertDatabaseHas('calendar_feeds', [
        'id' => $feed->id,
        'team_id' => $team->id,
        'name' => 'American Holidays',
        'url' => 'https://example.com/american.ics',
        'color' => CalendarColor::Indigo->value,
    ]);
});

test('can remove a team calendar feed', function () {
    $team = Team::factory()
        ->has(CalendarFeed::factory()->state([
            'name' => 'US Holidays',
            'url' => 'https://example.com/us.ics',
            'color' => CalendarColor::Red,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();
    $feed = $team->calendarFeeds()->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->call('delete', $feed->id)
        ->assertHasNoErrors()
        ->assertDontSee('US Holidays');

    $this->assertDatabaseMissing('calendar_feeds', [
        'id' => $feed->id,
    ]);
});

test('shows failure details for a failing feed', function () {
    $team = Team::factory()
        ->has(CalendarFeed::factory()->failing()->state([
            'name' => 'Work Calendar',
            'url' => 'https://example.com/work.ics',
            'color' => CalendarColor::Red,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->assertSee('Failing')
        ->assertSee('1 feed is failing.')
        ->assertSee('Last failed')
        ->assertSee('The calendar server responded with HTTP 401.')
        ->assertSee('Secret address in iCal format');
});

test('summarizes failing feeds from one host in a single notice', function () {
    $team = Team::factory()
        ->has(CalendarFeed::factory()->failing()->count(3)->sequence(
            ['url' => 'https://calendar.google.com/a.ics'],
            ['url' => 'https://calendar.google.com/b.ics'],
            ['url' => 'https://calendar.google.com/c.ics'],
        ))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    $component = Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->assertSee('3 feeds are failing, all from calendar.google.com.');

    expect(substr_count($component->html(), 'Secret address in iCal format'))->toBe(1)
        ->and(substr_count($component->html(), 'Last failed'))->toBe(3);
});

test('summarizes failing feeds from different hosts without naming a host', function () {
    $team = Team::factory()
        ->has(CalendarFeed::factory()->failing()->count(2)->sequence(
            ['url' => 'https://calendar.google.com/a.ics'],
            ['url' => 'https://example.com/b.ics'],
        ))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->assertSee('2 feeds are failing.')
        ->assertDontSee('all from');
});

test('does not show the failing notice when every feed is healthy', function () {
    $team = Team::factory()->has(CalendarFeed::factory())->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->assertDontSee('failing.')
        ->assertDontSee('Secret address in iCal format');
});

test('updating a feed url clears its failure state once the new url loads', function () {
    Storage::fake();
    Http::fake(['https://example.com/work-fixed.ics' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))]);

    $team = Team::factory()
        ->has(CalendarFeed::factory()->failing()->state([
            'name' => 'Work Calendar',
            'url' => 'https://example.com/work.ics',
            'color' => CalendarColor::Red,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();
    $feed = $team->calendarFeeds()->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->call('edit', $feed->id)
        ->set('form.url', 'https://example.com/work-fixed.ics')
        ->call('save')
        ->assertHasNoErrors();

    $feed->refresh();

    expect($feed->isFailing())->toBeFalse()
        ->and($feed->last_error)->toBeNull();
});

test('validates calendar feed input', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->set('form.name', '')
        ->set('form.url', 'not-a-url')
        ->set('form.color', '#ffffff')
        ->call('save')
        ->assertHasErrors(['form.name', 'form.url', 'form.color']);
});

test('the form says when a team already has the most calendars it can have', function () {
    Http::fake();
    $team = Team::factory()->create();
    $user = User::factory()->memberOf($team)->create();
    CalendarFeed::factory()->count(CalendarFeed::MAX_PER_TEAM)->for($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->set('form.name', 'One more')
        ->set('form.url', 'https://example.com/more.ics')
        ->set('form.color', CalendarColor::Green->value)
        ->call('save')
        ->assertHasErrors(['form.url' => 'A team can have up to 25 calendars.']);

    expect($team->calendarFeeds()->count())->toBe(CalendarFeed::MAX_PER_TEAM);
});
