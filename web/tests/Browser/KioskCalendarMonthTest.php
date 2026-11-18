<?php

use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\actingAs;

beforeEach(function () {
    Storage::fake();

    $team = Team::factory()->create(['timezone' => 'America/Chicago', 'screensaver_after' => 0, 'return_home_after' => 0]);
    $user = User::factory()->memberOf($team)->create();
    CalendarFeed::factory()->for($team)->create(['url' => 'https://example.com/family.ics']);

    $today = CarbonImmutable::now('America/Chicago')->format('Ymd');
    $events = collect(['090000' => 'Make Ship Happen Recording', '123000' => 'Edit Recording', '190000' => 'Resonate Concert'])
        ->map(fn (string $title, string $time) => <<<ICS
BEGIN:VEVENT
UID:{$time}
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:{$today}T{$time}
SUMMARY:{$title}
END:VEVENT
ICS)
        ->implode("\n");

    Http::fake([
        'https://example.com/family.ics' => Http::response("BEGIN:VCALENDAR\nVERSION:2.0\nPRODID:-//Sunny//Tests//EN\n{$events}\nEND:VCALENDAR"),
    ]);

    actingAs($user);
});

$byKey = fn (string $prefix): string => "[...document.querySelectorAll('div')].filter((el) => el.getAttribute('wire:key')?.startsWith('{$prefix}'))";

test('the month view fills a large display', function () use ($byKey) {
    visit(route('kiosk.calendar', ['format' => 'month'], absolute: false))
        ->resize(1920, 1080)
        ->assertNoJavaScriptErrors()
        ->waitForText('Make Ship Happen Recording')
        ->assertSee('+1 events')
        ->assertScript("Math.round(innerHeight - {$byKey('calendar-day-')}.at(-1).getBoundingClientRect().bottom) <= 1");
});

test('month events keep their height when the display enlarges text', function () use ($byKey) {
    $page = visit(route('kiosk.calendar', ['format' => 'month'], absolute: false))
        ->resize(1920, 400)
        ->assertNoJavaScriptErrors()
        ->waitForText('Make Ship Happen Recording');

    $page->script("document.head.insertAdjacentHTML('beforeend', '<style>.text-xs { font-size: calc(0.75rem * 1.3) !important } .text-sm { font-size: calc(0.875rem * 1.3) !important }</style>')");

    $page->assertScript("{$byKey('calendar-month-event-')}.length", 2)
        ->assertScript("{$byKey('calendar-month-event-')}.every((chip) => chip.clientHeight >= chip.scrollHeight)");
});
