<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Calendar\DeleteCalendarFeed;
use App\Models\CalendarFeed;
use App\Models\User;

test('it deletes a calendar feed on the current team', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create(['name' => 'Crew Calendar']);

    SunnyServer::actingAs($user)
        ->tool(DeleteCalendarFeed::class, ['id' => $feed->id])
        ->assertOk()
        ->assertSee("Calendar feed \"Crew Calendar\" (ID {$feed->id}) deleted.");

    expect(CalendarFeed::find($feed->id))->toBeNull();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(DeleteCalendarFeed::class)
        ->assertHasErrors();
});

test('it cannot delete a feed belonging to another team', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(DeleteCalendarFeed::class, ['id' => $feed->id])
        ->assertHasErrors(['Calendar feed not found.']);

    expect($feed->fresh())->not->toBeNull();
});
