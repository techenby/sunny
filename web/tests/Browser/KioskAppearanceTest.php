<?php

use App\Enums\Appearance;
use App\Models\Team;
use App\Models\User;

use function Pest\Laravel\actingAs;

test('the kiosk appearance does not carry over to the rest of the app', function () {
    $team = Team::factory()->create(['appearance' => Appearance::Dark]);
    $user = User::factory()->memberOf($team)->create();

    actingAs($user);

    $page = visit(route('kiosk.calendar', absolute: false))->inLightMode();

    $page->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.classList.contains("dark")')
        ->assertScript('localStorage.getItem("flux.appearance")', null);

    $page->navigate(route('dashboard', absolute: false))
        ->assertNoJavaScriptErrors()
        ->assertScript('document.documentElement.classList.contains("dark")', false);
});
