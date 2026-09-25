<?php

use App\Models\User;

test('displays the landing page for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(asset('icon.svg'))
        ->assertSee('Log in')
        ->assertSee('Register')
        ->assertSee('Your household, organized');
});

test('shows dashboard link for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard');
});
