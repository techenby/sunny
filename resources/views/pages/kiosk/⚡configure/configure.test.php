<?php

use App\Enums\CalendarColor;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

it('renders successfully', function () {
    $team = Team::factory()
        ->has(CalendarFeed::factory()->state([
            'name' => 'Family Calendar',
            'url' => 'https://example.com/family.ics',
            'color' => CalendarColor::Green,
        ]))
        ->create();
    $user = User::factory()->memberOf($team)->create();

    actingAs($user)
        ->get(route('kiosk.configure'))
        ->assertOk()
        ->assertSee('Family Calendar');

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure')
        ->assertStatus(200)
        ->assertSee('Family Calendar');
});

it('can add a calendar feed', function () {
    $team = Team::factory()->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure')
        ->set('feedName', 'Brazilian Holidays')
        ->set('feedUrl', 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=BR&year=2026')
        ->set('feedColor', CalendarColor::Green->value)
        ->call('saveFeed')
        ->assertHasNoErrors()
        ->assertSet('feedName', '')
        ->assertSet('feedUrl', '')
        ->assertSet('feedColor', CalendarColor::Blue->value);

    $this->assertDatabaseHas('calendar_feeds', [
        'team_id' => $team->id,
        'name' => 'Brazilian Holidays',
        'url' => 'https://worldpublicholiday.com/calendar-feeds/feed.ics?country=BR&year=2026',
        'color' => CalendarColor::Green->value,
    ]);
});

it('can edit a team calendar feed', function () {
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
        ->test('pages::kiosk.configure')
        ->call('editFeed', $feed->id)
        ->assertSet('editingFeedId', $feed->id)
        ->assertSet('feedName', 'US Holidays')
        ->set('feedName', 'American Holidays')
        ->set('feedUrl', 'https://example.com/american.ics')
        ->set('feedColor', CalendarColor::Indigo->value)
        ->call('saveFeed')
        ->assertHasNoErrors()
        ->assertSet('editingFeedId', null);

    $this->assertDatabaseHas('calendar_feeds', [
        'id' => $feed->id,
        'team_id' => $team->id,
        'name' => 'American Holidays',
        'url' => 'https://example.com/american.ics',
        'color' => CalendarColor::Indigo->value,
    ]);
});

it('can remove a team calendar feed', function () {
    $team = Team::factory()
        ->has(CalendarFeed::factory())
        ->create();
    $user = User::factory()->memberOf($team)->create();
    $feed = $team->calendarFeeds()->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure')
        ->call('deleteFeed', $feed->id)
        ->assertHasNoErrors();

    $this->assertDatabaseMissing('calendar_feeds', [
        'id' => $feed->id,
    ]);
});

it('validates calendar feed input', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure')
        ->set('feedName', '')
        ->set('feedUrl', 'not-a-url')
        ->set('feedColor', '#ffffff')
        ->call('saveFeed')
        ->assertHasErrors(['feedName', 'feedUrl', 'feedColor']);
});
