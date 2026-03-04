<?php

use App\Models\User;

test('displays the landing page for guests', function () {
    $this->get('/')
        ->assertOk()
        ->assertSee('Log in')
        ->assertSee('Register')
        ->assertSee('Your household, organized');
});

test('shows dashboard link for authenticated users', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->get('/')
        ->assertOk()
        ->assertSee('Dashboard');
});
