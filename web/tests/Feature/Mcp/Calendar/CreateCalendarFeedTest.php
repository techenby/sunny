<?php

use App\Enums\CalendarColor;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Calendar\CreateCalendarFeed;
use App\Models\User;

test('it creates a calendar feed for the current team', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateCalendarFeed::class, [
            'name' => 'Crew Calendar',
            'url' => 'https://example.com/crew.ics',
            'color' => CalendarColor::Green->value,
        ])
        ->assertOk()
        ->assertSee('Calendar feed "Crew Calendar" created with ID');

    $this->assertDatabaseHas('calendar_feeds', [
        'team_id' => $user->currentTeam->id,
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
        'color' => CalendarColor::Green->value,
    ]);
});

test('it requires a name, url, and color', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateCalendarFeed::class, [])
        ->assertHasErrors();

    expect($user->currentTeam->calendarFeeds()->count())->toBe(0);
});

test('it rejects a color that is not one of the calendar colors', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateCalendarFeed::class, [
            'name' => 'Crew Calendar',
            'url' => 'https://example.com/crew.ics',
            'color' => 'chartreuse',
        ])
        ->assertHasErrors(['The color must be one of:']);
});

test('it rejects an invalid url', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateCalendarFeed::class, [
            'name' => 'Crew Calendar',
            'url' => 'not-a-url',
            'color' => CalendarColor::Blue->value,
        ])
        ->assertHasErrors(['The url must be a valid http or https URL pointing to an ICS calendar file.']);
});
