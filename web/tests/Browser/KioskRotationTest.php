<?php

use App\Models\CalendarFeed;
use App\Models\Team;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('rotated kiosk renders dropdowns inside the rotated page', function () {
    $team = Team::factory()
        ->has(CalendarFeed::factory()->failing())
        ->create(['rotation' => 90]);
    $user = User::factory()->memberOf($team)->create();

    actingAs($user);

    $page = visit(route('kiosk.calendar', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.body.dataset.rotation', '90')
        ->assertScript('getComputedStyle(document.body).transform !== "none"')
        // The failed-feed warning popover opens with Flux's panel styling,
        // not the polyfill's UA-like fallback (3px canvas border)...
        ->click('ui-dropdown:nth-of-type(1) > button')
        ->assertVisible('ui-dropdown:nth-of-type(1) > [popover]')
        ->assertSee("Couldn't load")
        ->assertScript('getComputedStyle(document.querySelector("ui-dropdown [popover]")).borderTopWidth', '1px');

    // While a popover is open Flux disables pointer events outside it (light dismiss),
    // so close the warning before interacting with the filter dropdown.
    $page->script('document.querySelector("ui-dropdown:nth-of-type(1) > button").click()');

    // The feed filter menu opens through the polyfill (class), staying
    // inside the page so it inherits the body rotation...
    $page->click('ui-dropdown:nth-of-type(2) > button')
        ->assertVisible('ui-dropdown:nth-of-type(2) > [popover]')
        ->assertScript('document.querySelector("ui-dropdown:nth-of-type(2) > [popover]").className.includes(":popover-open")')
        // ...anchored "below" its trigger in rotated space (viewport-left of the button).
        ->assertScript('document.querySelector("ui-dropdown:nth-of-type(2) > [popover]").getBoundingClientRect().right <= document.querySelector("ui-dropdown:nth-of-type(2) > button").getBoundingClientRect().left');
});

test('unrotated kiosk keeps the native popover behavior', function () {
    $team = Team::factory()->create(['rotation' => 0]);
    $user = User::factory()->memberOf($team)->create();

    actingAs($user);

    $page = visit(route('kiosk.calendar', absolute: false));

    $page->assertNoJavaScriptErrors()
        ->click('ui-dropdown > button')
        ->assertVisible('ui-dropdown [popover]')
        ->assertScript('document.querySelector("ui-dropdown [popover]").matches(":popover-open")');
});
