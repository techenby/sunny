<?php

use App\Enums\CalendarColor;
use App\Mcp\Tools\Calendar\UpdateCalendarFeed;
use App\Models\CalendarFeed;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it updates the provided fields on a calendar feed', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Crew Calendar',
        'color' => CalendarColor::Blue,
    ]);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateCalendarFeed::class, [
            'id' => $feed->id,
            'name' => 'Ship Calendar',
            'color' => CalendarColor::Red->value,
        ])
        ->assertOk()
        ->assertSee("Calendar feed \"Ship Calendar\" (ID {$feed->id}) updated.");

    $feed->refresh();

    expect($feed->name)->toBe('Ship Calendar')
        ->and($feed->color)->toBe(CalendarColor::Red)
        ->and($feed->url)->not->toBeNull();
});

test('it cannot update a feed belonging to another team', function () {
    $user = User::factory()->create();
    $otherTeamFeed = CalendarFeed::factory()->create(['name' => 'Marine Calendar']);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateCalendarFeed::class, [
            'id' => $otherTeamFeed->id,
            'name' => 'Hijacked',
        ])
        ->assertHasErrors(['Calendar feed not found.']);

    expect($otherTeamFeed->refresh()->name)->toBe('Marine Calendar');
});

test('it rejects a color that is not one of the calendar colors', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create();

    SunnyTestServer::actingAs($user)
        ->tool(UpdateCalendarFeed::class, [
            'id' => $feed->id,
            'color' => 'chartreuse',
        ])
        ->assertHasErrors(['The color must be one of:']);
});

test('it requires at least one field to update', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create();

    SunnyTestServer::actingAs($user)
        ->tool(UpdateCalendarFeed::class, ['id' => $feed->id])
        ->assertHasErrors(['Provide at least one field to update: name, url, or color.']);
});

test('it refuses a blank name or url and leaves the feed unchanged', function (string $field, string $message) {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create([
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
    ]);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateCalendarFeed::class, [
            'id' => $feed->id,
            $field => '',
        ])
        ->assertHasErrors([$message]);

    $feed->refresh();

    expect($feed->name)->toBe('Crew Calendar')
        ->and($feed->url)->toBe('https://example.com/crew.ics');
})->with([
    'name' => ['name', 'The name cannot be blank.'],
    'url' => ['url', 'The url cannot be blank.'],
]);
