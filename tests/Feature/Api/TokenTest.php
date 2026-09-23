<?php

use App\Models\User;

test('valid credentials return a token', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonStructure(['token']);

    expect($user->tokens()->count())->toBe(1);
});

test('invalid credentials are rejected', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'iPhone',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect($user->tokens()->count())->toBe(0);
});

test('token requests are rate limited', function () {
    $user = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone',
        ])->assertUnprocessable();
    }

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])->assertTooManyRequests();

    expect($user->tokens()->count())->toBe(0);
});

test('rate limit is scoped per email', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone',
        ]);
    }

    $this->postJson(route('api.token'), [
        'email' => $other->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])->assertOk();
});
