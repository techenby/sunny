<?php

use App\Actions\Kiosk\CreateFeed;
use App\Enums\CalendarColor;
use App\Models\Team;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake();
});

test('it downloads a new feed straight away', function () {
    $ics = file_get_contents(base_path('tests/Fixtures/ics/events.ics'));

    Http::fake(['https://example.com/crew.ics' => Http::response($ics)]);

    $feed = (new CreateFeed)->handle(Team::factory()->create(), [
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
        'color' => CalendarColor::Blue->value,
    ]);

    Http::assertSentCount(1);

    expect(Storage::get($feed->icsPath()))->toBe($ics)
        ->and($feed->fresh()->ics_fetched_at)->not->toBeNull()
        ->and($feed->fresh()->isFailing())->toBeFalse();
});

test('it marks a new feed failing when the download fails', function () {
    Http::fake(['https://example.com/crew.ics' => Http::response('Not found', 404)]);

    $feed = (new CreateFeed)->handle(Team::factory()->create(), [
        'name' => 'Crew Calendar',
        'url' => 'https://example.com/crew.ics',
        'color' => CalendarColor::Blue->value,
    ]);

    Storage::assertMissing($feed->icsPath());

    expect($feed->fresh()->isFailing())->toBeTrue()
        ->and($feed->fresh()->last_error)->toBe('The calendar server responded with HTTP 404.');
});
