<?php

use App\Models\User;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the admin dashboard', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('admin.dashboard'))
        ->assertOk();
});
