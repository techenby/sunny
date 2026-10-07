<?php

use App\Enums\CalendarColor;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Calendar\ListCalendarFeeds;
use App\Models\CalendarFeed;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

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
        ->assertStructuredContent([
            'feeds' => [
                [
                    'id' => $feed->id,
                    'name' => 'Crew Calendar',
                    'url' => 'https://example.com/crew.ics',
                    'color' => '#2563eb',
                    'color_name' => 'Blue',
                    'last_fetched_at' => $feed->last_fetched_at->toIso8601String(),
                    'status' => 'ok',
                    'last_error' => null,
                ],
            ],
        ]);
});

test('it shows the failing status and last error for a broken feed', function () {
    $user = User::factory()->create();
    CalendarFeed::factory()->failing()->for($user->currentTeam)->create([
        'name' => 'Broken Calendar',
    ]);

    SunnyServer::actingAs($user)
        ->tool(ListCalendarFeeds::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('feeds.0.status', 'failing')
            ->where('feeds.0.last_error', 'The calendar server responded with HTTP 401.')
            ->etc());
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
        ->assertStructuredContent([
            'feeds' => [],
            'note' => 'No calendar feeds have been added yet. Use the create-calendar-feed tool to add one.',
        ]);
});
