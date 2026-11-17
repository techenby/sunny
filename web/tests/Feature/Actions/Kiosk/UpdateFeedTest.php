<?php

use App\Actions\Kiosk\UpdateFeed;
use App\Models\CalendarFeed;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();

    $this->ics = file_get_contents(base_path('tests/Fixtures/ics/events.ics'));
});

test('changing the url downloads the new feed straight away', function () {
    $newIcs = str_replace('Crew Meeting', 'Navigation Briefing', $this->ics);

    Http::fake(['https://example.com/navigation.ics' => Http::response($newIcs)]);

    $feed = CalendarFeed::factory()->failing()->withIcs($this->ics)->create(['url' => 'https://example.com/crew.ics']);

    (new UpdateFeed)->handle($feed, ['url' => 'https://example.com/navigation.ics']);

    Http::assertSentCount(1);

    expect(Storage::get($feed->icsPath()))->toBe($newIcs)
        ->and($feed->fresh()->isFailing())->toBeFalse();
});

test('changing the url drops the old copy when the new feed fails', function () {
    Http::fake(['https://example.com/navigation.ics' => Http::response('Not found', 404)]);

    $feed = CalendarFeed::factory()->withIcs($this->ics)->create(['url' => 'https://example.com/crew.ics']);

    (new UpdateFeed)->handle($feed, ['url' => 'https://example.com/navigation.ics']);

    Storage::assertMissing($feed->icsPath());

    expect($feed->fresh()->ics_fetched_at)->toBeNull()
        ->and($feed->fresh()->isFailing())->toBeTrue();
});

test('changing only the name keeps the copy without downloading', function () {
    Http::fake();

    $feed = CalendarFeed::factory()->withIcs($this->ics)->create(['url' => 'https://example.com/crew.ics']);

    (new UpdateFeed)->handle($feed, ['name' => 'Ship Calendar', 'url' => 'https://example.com/crew.ics']);

    Http::assertNothingSent();

    expect(Storage::get($feed->icsPath()))->toBe($this->ics)
        ->and($feed->fresh()->ics_fetched_at)->not->toBeNull();
});
