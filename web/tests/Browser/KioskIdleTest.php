<?php

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Models\CalendarFeed;
use App\Models\Routine;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Http;

use function Pest\Laravel\actingAs;

$idleFor = fn (int $seconds): string => "Alpine.\$data(document.querySelector('[data-kiosk-screensaver]')).lastActivityAt -= {$seconds} * 1000";

test('the screensaver covers an idle kiosk until it is tapped', function () use ($idleFor) {
    $team = Team::factory()->create(['screensaver_after' => 1, 'return_home_after' => 0]);
    $user = User::factory()->memberOf($team)->create();

    actingAs($user);

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->assertMissing('[data-kiosk-screensaver]')
        ->script($idleFor(61));

    $page->wait(1.5)
        ->assertVisible('[data-kiosk-screensaver]')
        ->assertNotPresent('[data-kiosk-screensaver][data-night]')
        ->click('[data-kiosk-screensaver]')
        ->assertMissing('[data-kiosk-screensaver]')
        ->assertPathIs(route('kiosk.lists', absolute: false));
});

test('an idle kiosk returns to the calendar', function () use ($idleFor) {
    $team = Team::factory()->create(['screensaver_after' => 0, 'return_home_after' => 5]);
    $user = User::factory()->memberOf($team)->create();

    actingAs($user);

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->script($idleFor(5 * 60));

    $page->wait(2)
        ->assertPathIs(route('kiosk.calendar', absolute: false))
        ->assertMissing('[data-kiosk-screensaver]');
});

test('a routine in progress holds off idling for 30 minutes', function () use ($idleFor) {
    $team = Team::factory()->create(['screensaver_after' => 5, 'return_home_after' => 10]);
    $user = User::factory()->memberOf($team)->create();
    $routine = Routine::factory()->for($team)->daily()->assignedTo($user)->create();
    RoutineStep::factory()->for($routine)->count(2)->create();

    resolve(GenerateRoutineOccurrences::class)->forDate($team, $team->today());
    $step = RoutineOccurrenceStep::query()->first();

    actingAs($user);

    $page = visit(route('kiosk.routines', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->assertNotPresent('[data-routine-in-progress]')
        ->click("ui-checkbox[value=\"{$step->id}\"]")
        ->assertPresent('[data-routine-in-progress]')
        ->script($idleFor(20 * 60));

    $page->wait(1.5)
        ->assertMissing('[data-kiosk-screensaver]')
        ->assertPathIs(route('kiosk.routines', absolute: false))
        ->script($idleFor(11 * 60));

    $page->wait(2)
        ->assertPathIs(route('kiosk.calendar', absolute: false))
        ->assertVisible('[data-kiosk-screensaver]');
});

test('the screensaver turns into a night clock during night hours', function () use ($idleFor) {
    $now = CarbonImmutable::now('America/Chicago');
    $team = Team::factory()->create([
        'timezone' => 'America/Chicago',
        'screensaver_after' => 1,
        'return_home_after' => 0,
        'night_starts_at' => $now->subHour()->format('H:i'),
        'night_ends_at' => $now->addHour()->format('H:i'),
    ]);
    $user = User::factory()->memberOf($team)->create();

    actingAs($user);

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->script($idleFor(61));

    $page->wait(1.5)
        ->assertVisible('[data-kiosk-screensaver][data-night]');
});

test('the screensaver shows the next event', function () use ($idleFor) {
    $team = Team::factory()->create(['timezone' => 'America/Chicago', 'screensaver_after' => 1, 'return_home_after' => 0]);
    $user = User::factory()->memberOf($team)->create();
    CalendarFeed::factory()->for($team)->create(['url' => 'https://example.com/family.ics']);
    $startsAt = CarbonImmutable::now('America/Chicago')->addMinutes(31)->format('Ymd\THis');

    Http::fake([
        'https://example.com/family.ics' => Http::response(<<<ICS
BEGIN:VCALENDAR
VERSION:2.0
PRODID:-//Sunny//Tests//EN
BEGIN:VEVENT
UID:soccer
DTSTAMP:20260501T120000Z
DTSTART;TZID=America/Chicago:{$startsAt}
SUMMARY:Soccer practice
END:VEVENT
END:VCALENDAR
ICS),
    ]);

    actingAs($user);

    $page = visit(route('kiosk.lists', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->script($idleFor(61));

    $page->wait(1.5)
        ->assertVisible('[data-kiosk-screensaver]')
        ->waitForText('Soccer practice')
        ->assertScript("/in \\d+ min/.test(document.querySelector('[data-screensaver-next-event]').textContent)");
});
