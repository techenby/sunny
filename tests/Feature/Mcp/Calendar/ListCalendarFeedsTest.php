<?php

use App\Enums\CalendarColor;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Calendar\ListCalendarFeeds;
use App\Models\CalendarFeed;
use App\Models\User;

test('it lists the feeds for the current team', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
        'color' => CalendarColor::Blue,
        'last_fetched_at' => now(),
    ]);

    SunnyServer::actingAs($user)
        ->tool(ListCalendarFeeds::class)
        ->assertOk()
        ->assertSee("Crew Calendar (ID: {$feed->id})")
        ->assertSee('URL: https://example.com/crew.ics')
        ->assertSee('Color: Blue (#2563eb)')
        ->assertSee('Status: ok');
});

test('it shows the failing status and last error for a broken feed', function () {
    $user = User::factory()->create();
    CalendarFeed::factory()->failing()->for($user->currentTeam)->create([
        'name' => 'Broken Calendar',
    ]);

    SunnyServer::actingAs($user)
        ->tool(ListCalendarFeeds::class)
        ->assertOk()
        ->assertSee('Status: failing')
        ->assertSee('Last error: The calendar server responded with HTTP 401.');
});

test('it does not list feeds belonging to other teams', function () {
    $user = User::factory()->create();
    CalendarFeed::factory()->for($user->currentTeam)->create(['name' => 'Crew Calendar']);
    CalendarFeed::factory()->create(['name' => 'Marine Calendar']);

    SunnyServer::actingAs($user)
        ->tool(ListCalendarFeeds::class)
        ->assertOk()
        ->assertSee('Crew Calendar')
        ->assertDontSee('Marine Calendar');
});

test('it explains when the team has no feeds', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ListCalendarFeeds::class)
        ->assertOk()
        ->assertSee('No calendar feeds have been added yet.');
});
