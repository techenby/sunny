<?php

use App\Models\User;
use Illuminate\Support\Facades\Date;

beforeEach(function () {
    Date::setTestNow('2027-02-01 12:00:00');
    config(['auth.verification.grace_until' => '2027-01-04']);
});

test('an unverified user keeps using the api for a week after signing up', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk();

    $this->travel(8)->days();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertStatus(423)
        ->assertJsonPath('email_verification_required', true)
        ->assertJsonPath('message', 'Verify your email address to keep using SunnyHome.');

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Toolbox', 'type' => 'item'])
        ->assertStatus(423);
});

test('accounts that already exist keep using the api until the configured date', function () {
    $user = User::factory()->unverified()->create(['created_at' => '2026-06-01']);

    $this->travelTo('2027-01-04 23:00:00');

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk();

    $this->travelTo('2027-01-05 00:00:01');

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertStatus(423);
});

test('an unverified user can still manage their account after the grace period', function () {
    $user = User::factory()->unverified()->create(['created_at' => '2026-06-01']);

    $this->actingAs($user)->getJson(route('api.user'))->assertOk();
    $this->actingAs($user)->patchJson(route('api.user.update'), ['email' => 'nami@example.com'])->assertOk();
    $this->actingAs($user)->postJson(route('api.user.verification.send'))->assertAccepted();
});

test('verified users are never asked to verify', function () {
    $user = User::factory()->create(['created_at' => '2026-06-01']);

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk();
});

test('the user tells the app when its grace period ends', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->getJson(route('api.user'))
        ->assertJsonPath('data.email_verification_grace_ends_at', '2027-02-08T12:00:00.000000Z');

    $user->markEmailAsVerified();

    $this->actingAs($user)
        ->getJson(route('api.user'))
        ->assertJsonPath('data.email_verification_grace_ends_at', null);
});
