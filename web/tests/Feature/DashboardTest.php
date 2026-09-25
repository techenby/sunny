<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get('/any-team/dashboard')
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create())
        ->get(route('dashboard'))
        ->assertOk();
});
