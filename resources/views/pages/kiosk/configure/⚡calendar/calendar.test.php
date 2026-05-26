<?php

use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
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
        ->assertSee('Family Calendar');

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.calendar')
        ->assertStatus(200)
        ->assertSee('Family Calendar');
});

test('can add a calendar feed', function () {
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
