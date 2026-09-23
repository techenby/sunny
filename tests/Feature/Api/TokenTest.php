<?php

use App\Models\User;
use Laravel\Sanctum\PersonalAccessToken;

test('valid credentials return a token', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonStructure(['token', 'expires_at']);

    expect($user->tokens()->count())->toBe(1);
});

test('issued tokens expire after 30 days', function () {
    $this->freezeSecond();
    $user = User::factory()->create();

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])->assertJsonPath('expires_at', now()->addDays(30)->toIso8601String());

    expect($user->tokens()->sole()->expires_at->equalTo(now()->addDays(30)))->toBeTrue();
});

test('expired tokens are rejected', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone', ['*'], now()->subMinute())->plainTextToken;

    $this->withToken($token)
        ->getJson(route('api.user'))
        ->assertUnauthorized();
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

test('guests cannot refresh a token', function () {
    $this->postJson(route('api.token.refresh'))->assertUnauthorized();
});

test('refresh swaps the current token for a new one', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $current = $user->createToken('iPhone', ['*'], now()->addDay());

    $response = $this->withToken($current->plainTextToken)
        ->postJson(route('api.token.refresh'))
        ->assertOk()
        ->assertJsonPath('expires_at', now()->addDays(30)->toIso8601String());

    $refreshed = PersonalAccessToken::findToken($response->json('token'));

    expect($refreshed->name)->toBe('iPhone')
        ->and($refreshed->tokenable->is($user))->toBeTrue()
        ->and(PersonalAccessToken::find($current->accessToken->id))->toBeNull();

    $this->app['auth']->forgetGuards();

    $this->withToken($current->plainTextToken)
        ->getJson(route('api.user'))
        ->assertUnauthorized();
});

test('refresh keeps the token abilities', function () {
    $user = User::factory()->create();
    $current = $user->createToken('iPhone', ['items:read']);

    $response = $this->withToken($current->plainTextToken)
        ->postJson(route('api.token.refresh'))
        ->assertOk();

    expect(PersonalAccessToken::findToken($response->json('token'))->abilities)->toBe(['items:read']);
});

test('refresh leaves other tokens alone', function () {
    $user = User::factory()->create();
    $current = $user->createToken('iPhone');
    $other = $user->createToken('iPad');

    $this->withToken($current->plainTextToken)
        ->postJson(route('api.token.refresh'))
        ->assertOk();

    expect(PersonalAccessToken::find($other->accessToken->id))->not->toBeNull()
        ->and($user->tokens()->count())->toBe(2);
});

test('session authenticated requests cannot refresh', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.token.refresh'))
        ->assertBadRequest();
});
