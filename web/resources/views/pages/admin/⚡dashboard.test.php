<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users cannot visit the admin dashboard in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('Andy can visit the admin dashboard in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create(['email' => 'andy@techenby.com']))
        ->get(route('admin.dashboard'))
        ->assertOk();
});
