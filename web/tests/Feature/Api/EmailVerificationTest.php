<?php

use App\Models\User;

test('an unverified user is asked to verify before using their household', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertStatus(423)
        ->assertJsonPath('email_verification_required', true)
        ->assertJsonPath('message', 'Verify your email address to keep using SunnyHome.');

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Toolbox', 'type' => 'item'])
        ->assertStatus(423);

    $this->actingAs($user)
        ->postJson(route('api.user.onboarding'))
        ->assertStatus(423);
});

test('an unverified user can still sign in and manage their account', function () {
    $user = User::factory()->unverified()->create();

    $this->postJson(route('api.token'), ['email' => $user->email, 'password' => 'password', 'device_name' => 'iPhone'])->assertOk();
    $this->actingAs($user)->getJson(route('api.user'))->assertOk();
    $this->actingAs($user)->patchJson(route('api.user.update'), ['email' => 'nami@example.com'])->assertOk();
    $this->actingAs($user)->postJson(route('api.user.verification.send'))->assertAccepted();
});

test('verified users can use their household', function () {
    $this->actingAs(User::factory()->create())
        ->getJson(route('api.sync'))
        ->assertOk();
});
