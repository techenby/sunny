<?php

use App\Mcp\Tools\Calendar\DeleteCalendarFeed;
use App\Models\CalendarFeed;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it deletes a calendar feed on the current team', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create(['name' => 'Crew Calendar']);

    SunnyTestServer::actingAs($user)
        ->tool(DeleteCalendarFeed::class, ['id' => $feed->id])
        ->assertOk()
        ->assertSee("Calendar feed \"Crew Calendar\" (ID {$feed->id}) deleted.");

    expect(CalendarFeed::find($feed->id))->toBeNull();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteCalendarFeed::class)
        ->assertHasErrors();
});

test('it cannot delete a feed belonging to another team', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteCalendarFeed::class, ['id' => $feed->id])
        ->assertHasErrors(['Calendar feed not found.']);

    expect($feed->fresh())->not->toBeNull();
});
