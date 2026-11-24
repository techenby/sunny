<?php

use App\Models\User;

$userFields = ['id', 'name', 'email', 'email_verified_at', 'two_factor_enabled', 'current_team_id', 'onboarded_at', 'created_at', 'updated_at'];

test('guests cannot fetch the user', function () {
    $this->getJson(route('api.user'))->assertUnauthorized();
});

test('the user endpoint returns only the public user fields', function () use ($userFields) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertExactJsonStructure(['data' => $userFields])
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.current_team_id', $user->current_team_id)
        ->assertJsonPath('data.two_factor_enabled', false);
});

test('the user endpoint reports when two factor is enabled', function () {
    $this->actingAs(User::factory()->withTwoFactor()->create())
        ->getJson(route('api.user'))
        ->assertJsonPath('data.two_factor_enabled', true);
});

test('the token response contains only the public user fields and the token', function () use ($userFields) {
    $user = User::factory()->create();

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])
        ->assertOk()
        ->assertExactJsonStructure([...$userFields, 'token', 'expires_at']);
});

test('the api slows down one account that sends too many requests', function () {
    $user = User::factory()->create();

    foreach (range(1, 240) as $attempt) {
        $this->actingAs($user)->getJson(route('api.user'))->assertOk();
    }

    $this->actingAs($user)->getJson(route('api.user'))->assertTooManyRequests();
    $this->actingAs(User::factory()->create())->getJson(route('api.user'))->assertOk();
});
