<?php

use App\Models\User;

test('user menus escape the user name once', function () {
    $this->actingAs(User::factory()->create(['name' => "Vidal O'Keefe & Co"]))
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Vidal O&#039;Keefe &amp; Co', false)
        ->assertDontSee('&amp;#039;', false)
        ->assertDontSee('&amp;amp;', false);
});
