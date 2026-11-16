<?php

use App\Models\User;

use function Pest\Laravel\actingAs;

test('the sidebar profile shows apostrophes and ampersands in names', function () {
    actingAs(User::factory()->create(['name' => "Vidal O'Keefe & Co"]));

    visit(route('dashboard', absolute: false))
        ->resize(1440, 900)
        ->assertNoJavaScriptErrors()
        ->assertSeeIn('[data-test="sidebar-menu-button"]', "Vidal O'Keefe & Co")
        ->assertDontSeeIn('[data-test="sidebar-menu-button"]', '&#039;');
});
