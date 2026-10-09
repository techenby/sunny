<?php

use App\Models\User;

test('displays the landing page for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee(asset('icon.svg'))
        ->assertSee('Log in')
        ->assertSee('Register')
        ->assertSee('Get started')
        ->assertSee('Your household, organized')
        ->assertSeeInOrder(['id="features"', 'id="kiosk"', 'id="mobile"', 'id="assistant"'], false)
        ->assertDontSee('Soon');
});

test('shows dashboard link for authenticated users', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard')
        ->assertSee('Open your dashboard')
        ->assertDontSee('Get started');
});
