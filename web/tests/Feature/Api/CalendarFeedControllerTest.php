<?php

use App\Enums\CalendarColor;
use App\Enums\TeamRole;
use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

test('guests cannot access calendar feeds', function () {
    $this->getJson(route('api.calendar-feeds.index', 'household'))->assertUnauthorized();
    $this->postJson(route('api.calendar-feeds.store', 'household'))->assertUnauthorized();
    $this->getJson(route('api.calendar-feeds.show', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.calendar-feeds.update', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.calendar-feeds.destroy', ['household', 1]))->assertUnauthorized();
});

test('index returns the team calendar feeds with their status', function () {
    $user = User::factory()->create();
    CalendarFeed::factory()->for($user->currentTeam)->create(['name' => 'Work', 'url' => 'https://example.com/work.ics']);
    CalendarFeed::factory()->for($user->currentTeam)->failing()->create(['name' => 'Family']);
    CalendarFeed::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.calendar-feeds.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Family')
        ->assertJsonPath('data.0.status', 'failing')
        ->assertJsonPath('data.0.last_error', 'The calendar server responded with HTTP 401.')
        ->assertJsonPath('data.1.name', 'Work')
        ->assertJsonPath('data.1.url', 'https://example.com/work.ics')
        ->assertJsonPath('data.1.status', 'ok');
});

test('index returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.calendar-feeds.index', $team))
        ->assertForbidden();
});

test('store creates a calendar feed and returns it', function () {
    Storage::fake();
    Http::fake(['https://example.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))]);

    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.calendar-feeds.store', $user->currentTeam), [
            'name' => 'Family',
            'url' => 'https://example.com/family.ics',
            'color' => CalendarColor::Green->value,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Family')
        ->assertJsonPath('data.url', 'https://example.com/family.ics')
        ->assertJsonPath('data.color', CalendarColor::Green->value)
        ->assertJsonPath('data.status', 'ok');

    $this->assertDatabaseHas('calendar_feeds', [
        'team_id' => $user->current_team_id,
        'name' => 'Family',
        'url' => 'https://example.com/family.ics',
    ]);
});

test('store returns the existing calendar feed when a client uuid is retried', function () {
    Storage::fake();
    Http::fake(['https://example.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))]);

    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $payload = ['name' => 'Family', 'url' => 'https://example.com/family.ics', 'color' => CalendarColor::Blue->value, 'client_uuid' => $uuid];

    $first = $this->actingAs($user)
        ->postJson(route('api.calendar-feeds.store', $user->currentTeam), $payload)
        ->assertCreated()
        ->assertJsonPath('data.client_uuid', $uuid);

    $this->actingAs($user)
        ->postJson(route('api.calendar-feeds.store', $user->currentTeam), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    Http::assertSentCount(1);
    expect(CalendarFeed::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store returns the existing calendar feed when a retry wins the race to insert it', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $racedId = null;

    DB::listen(function (QueryExecuted $query) use ($user, $uuid, &$racedId) {
        if ($racedId !== null || ! str_starts_with($query->sql, 'select') || ! str_contains($query->sql, 'calendar_feeds')) {
            return;
        }

        $racedId = 0;
        $racedId = DB::table('calendar_feeds')->insertGetId([
            'team_id' => $user->current_team_id,
            'name' => 'Family',
            'url' => 'https://example.com/family.ics',
            'color' => CalendarColor::Blue->value,
            'client_uuid' => $uuid,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    });

    $this->actingAs($user)
        ->postJson(route('api.calendar-feeds.store', $user->currentTeam), [
            'name' => 'Family',
            'url' => 'https://example.com/family.ics',
            'color' => CalendarColor::Blue->value,
            'client_uuid' => $uuid,
        ])
        ->assertOk()
        ->assertJsonPath('data.id', $racedId);

    expect(CalendarFeed::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store validates the calendar feed', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.calendar-feeds.store', $user->currentTeam), [
            'url' => 'webcal://example.com/family.ics',
            'color' => '#000000',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'url', 'color']);
});

test('store creates the calendar feed in the team from the url', function () {
    Storage::fake();
    Http::fake(['https://example.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))]);

    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->postJson(route('api.calendar-feeds.store', $team), [
            'name' => 'Family',
            'url' => 'https://example.com/family.ics',
            'color' => CalendarColor::Blue->value,
        ])
        ->assertCreated();

    expect($team->calendarFeeds()->count())->toBe(1)
        ->and($user->currentTeam->calendarFeeds()->count())->toBe(0);
});

test('show returns a calendar feed', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.calendar-feeds.show', [$user->currentTeam, $feed]))
        ->assertOk()
        ->assertJsonPath('data.id', $feed->id)
        ->assertJsonPath('data.url', $feed->url);
});

test('show returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.calendar-feeds.show', [$feed->team, $feed]))
        ->assertForbidden();
});

test('show returns 404 when the calendar feed belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $feed = CalendarFeed::factory()->for($team)->create();

    $this->actingAs($user)
        ->getJson(route('api.calendar-feeds.show', [$user->currentTeam, $feed]))
        ->assertNotFound();
});

test('update modifies a calendar feed', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create(['color' => CalendarColor::Blue]);

    $this->actingAs($user)
        ->patchJson(route('api.calendar-feeds.update', [$user->currentTeam, $feed]), [
            'name' => 'School',
            'color' => CalendarColor::Purple->value,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'School')
        ->assertJsonPath('data.color', CalendarColor::Purple->value)
        ->assertJsonPath('data.url', $feed->url);

    expect($feed->fresh())
        ->name->toBe('School')
        ->color->toBe(CalendarColor::Purple);
});

test('update clears the failing status when the url changes', function () {
    Storage::fake();
    Http::fake(['https://example.com/*' => Http::response(file_get_contents(base_path('tests/Fixtures/ics/events.ics')))]);

    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->failing()->create();

    $this->actingAs($user)
        ->patchJson(route('api.calendar-feeds.update', [$user->currentTeam, $feed]), ['url' => 'https://example.com/fixed.ics'])
        ->assertOk()
        ->assertJsonPath('data.url', 'https://example.com/fixed.ics')
        ->assertJsonPath('data.status', 'ok')
        ->assertJsonPath('data.last_error', null);
});

test('update validates the calendar feed', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patchJson(route('api.calendar-feeds.update', [$user->currentTeam, $feed]), ['name' => '', 'url' => 'not a url'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'url']);
});

test('update returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.calendar-feeds.update', [$feed->team, $feed]), ['name' => 'Nope'])
        ->assertForbidden();
});

test('destroy deletes a calendar feed', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.calendar-feeds.destroy', [$user->currentTeam, $feed]))
        ->assertNoContent();

    $this->assertModelMissing($feed);
});

test('destroy returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $feed = CalendarFeed::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.calendar-feeds.destroy', [$feed->team, $feed]))
        ->assertForbidden();

    $this->assertModelExists($feed);
});
