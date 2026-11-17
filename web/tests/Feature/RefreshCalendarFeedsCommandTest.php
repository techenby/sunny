<?php

use App\Actions\Calendars\FetchCalendarEvents;
use App\Models\CalendarFeed;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();

    $this->ics = file_get_contents(base_path('tests/Fixtures/ics/events.ics'));
});

test('it stores a fresh copy of every feed and records its health', function () {
    $this->travelTo(Date::parse('2026-05-05 08:00:00'));

    $crew = CalendarFeed::factory()->failing()->create(['url' => 'https://example.com/crew.ics']);
    $navigation = CalendarFeed::factory()->withIcs('BEGIN:VCALENDAR' . "\r\n" . 'END:VCALENDAR')->create(['url' => 'https://example.com/navigation.ics']);

    Http::fake([
        'https://example.com/*' => Http::response($this->ics),
    ]);

    $this->artisan('calendars:refresh')
        ->expectsOutputToContain('2 calendar feed(s) refreshed.')
        ->assertSuccessful();

    Http::assertSentCount(2);

    foreach ([$crew->fresh(), $navigation->fresh()] as $feed) {
        expect(Storage::get($feed->icsPath()))->toBe($this->ics)
            ->and($feed->ics_fetched_at->toDateTimeString())->toBe('2026-05-05 08:00:00')
            ->and($feed->last_fetched_at->toDateTimeString())->toBe('2026-05-05 08:00:00')
            ->and($feed->isFailing())->toBeFalse()
            ->and($feed->last_error)->toBeNull();
    }
});

test('a failed refresh keeps the last good copy and still shows its events', function (Closure $response, string $error) {
    $this->travelTo(Date::parse('2026-05-05 07:00:00'));

    $feed = CalendarFeed::factory()->withIcs($this->ics)->create(['url' => 'https://example.com/crew.ics']);

    $this->travelTo(Date::parse('2026-05-05 08:00:00'));

    Http::fake(['https://example.com/crew.ics' => $response]);

    $this->artisan('calendars:refresh')
        ->expectsOutputToContain('1 calendar feed(s) could not be refreshed.')
        ->assertSuccessful();

    $feed->refresh();
    $requestsSent = Http::recorded()->count();

    $events = resolve(FetchCalendarEvents::class)->handle($feed, 2, CarbonImmutable::parse('2026-05-05', 'America/Chicago'));

    Http::assertSentCount($requestsSent);

    expect(Storage::get($feed->icsPath()))->toBe($this->ics)
        ->and($feed->ics_fetched_at->toDateTimeString())->toBe('2026-05-05 07:00:00')
        ->and($feed->isFailing())->toBeTrue()
        ->and($feed->last_error)->toBe($error)
        ->and($events->pluck('title')->all())->toBe(['Crew Meeting', 'Shore Leave']);
})->with([
    'server error' => [fn () => Http::response('Unavailable', 503), 'The calendar server responded with HTTP 503.'],
    'connection failure' => [fn () => throw new ConnectionException('Timed out'), 'Could not connect to the calendar server.'],
    'not a calendar' => [fn () => Http::response('<!DOCTYPE html><title>Sign in</title>'), 'The calendar feed could not be read.'],
]);

test('one failing feed does not stop the others from refreshing', function () {
    $broken = CalendarFeed::factory()->create(['url' => 'https://example.com/broken.ics']);
    $offline = CalendarFeed::factory()->create(['url' => 'https://offline.example.com/calendar.ics']);
    $crew = CalendarFeed::factory()->create(['url' => 'https://example.com/crew.ics']);

    Http::fake([
        'https://example.com/broken.ics' => Http::response('Unauthorized', 401),
        'https://offline.example.com/*' => fn () => throw new ConnectionException('Timed out'),
        'https://example.com/crew.ics' => Http::response($this->ics),
    ]);

    $this->artisan('calendars:refresh')
        ->expectsOutputToContain('1 calendar feed(s) refreshed.')
        ->expectsOutputToContain('2 calendar feed(s) could not be refreshed.')
        ->assertSuccessful();

    expect($broken->fresh()->isFailing())->toBeTrue()
        ->and($offline->fresh()->isFailing())->toBeTrue()
        ->and($crew->fresh()->isFailing())->toBeFalse()
        ->and(Storage::get($crew->icsPath()))->toBe($this->ics);

    Storage::assertMissing($broken->icsPath());
    Storage::assertMissing($offline->icsPath());
});

test('the refresh is scheduled every fifteen minutes', function () {
    $event = collect(resolve(Schedule::class)->events())
        ->first(fn (Event $event): bool => str_contains((string) $event->command, 'calendars:refresh'));

    expect($event)->not->toBeNull()
        ->and($event->expression)->toBe('*/15 * * * *')
        ->and($event->withoutOverlapping)->toBeTrue();
});
