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
        ->assertSeeInOrder(['id="features"', 'id="kiosk"', 'id="mobile"', 'id="assistant"', 'id="built-by"'], false)
        ->assertDontSee('Soon')
        ->assertSee('Sunny Home')
        ->assertSee('Built by Andy Swick')
        ->assertSee('https://github.com/techenby/sunny')
        ->assertSee(route('privacy'))
        ->assertSee(route('terms'));
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

test('displays the privacy policy', function () {
    $this->get(route('privacy'))
        ->assertOk()
        ->assertSee('Privacy Policy')
        ->assertSee(route('terms'));
});

test('displays the terms of service', function () {
    $this->get(route('terms'))
        ->assertOk()
        ->assertSee('Terms of Service')
        ->assertSee(route('privacy'));
});
