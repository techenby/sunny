<?php

use App\Models\User;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Lockout;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
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

test('email is matched case-insensitively', function () {
    $user = User::factory()->create(['email' => 'person@example.com']);

    $this->postJson(route('api.token'), [
        'email' => 'Person@Example.com',
        'password' => 'password',
        'device_name' => 'iPhone',
    ])->assertOk()->assertJsonPath('id', $user->id);
});

test('invalid credentials fire the failed event', function () {
    Event::fake([Failed::class]);
    $user = User::factory()->create();

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'wrong-password',
        'device_name' => 'iPhone',
    ])->assertUnprocessable()->assertJsonPath('errors.email.0', trans('auth.failed'));

    Event::assertDispatched(Failed::class, fn (Failed $event): bool => $event->user->is($user));
});

test('outdated password hashes are rehashed on sign in', function () {
    $user = User::factory()->create();
    $outdatedHash = Hash::make('password', ['rounds' => 5]);
    DB::table('users')->where('id', $user->id)->update(['password' => $outdatedHash]);

    expect(Hash::needsRehash($user->fresh()->password))->toBeTrue();

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])->assertOk();

    expect($user->fresh()->password)->not->toBe($outdatedHash)
        ->and(Hash::needsRehash($user->fresh()->password))->toBeFalse();
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

test('lockouts fire the lockout event', function () {
    Event::fake([Lockout::class]);
    $user = User::factory()->create();

    foreach (range(1, 6) as $attempt) {
        $this->postJson(route('api.token'), [
            'email' => $user->email,
            'password' => 'wrong-password',
            'device_name' => 'iPhone',
        ]);
    }

    Event::assertDispatched(Lockout::class);
});

test('a successful sign in clears failed attempts', function () {
    $user = User::factory()->create();

    foreach (range(1, 2) as $round) {
        foreach (range(1, 4) as $attempt) {
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
        ])->assertOk();
    }
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
    $current = $user->createToken('iPhone', ['*'], now()->addDays(30));

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

test('refresh keeps the original token lifetime', function (?int $days) {
    $this->freezeSecond();
    $user = User::factory()->create();
    $current = $user->createToken('Raycast', ['*'], $days ? now()->addDays($days) : null);

    $this->travel(10)->days();

    $response = $this->withToken($current->plainTextToken)
        ->postJson(route('api.token.refresh'))
        ->assertOk()
        ->assertJsonPath('expires_at', $days ? now()->addDays($days)->toIso8601String() : null);

    expect(PersonalAccessToken::findToken($response->json('token'))->expires_at?->toIso8601String())
        ->toBe($days ? now()->addDays($days)->toIso8601String() : null);
})->with([
    '30 days' => [30],
    '1 year' => [365],
    'never' => [null],
]);

test('guests cannot log out', function () {
    $this->postJson(route('api.logout'))->assertUnauthorized();
});

test('logout revokes only the current token', function () {
    $user = User::factory()->create();
    $current = $user->createToken('iPhone');
    $other = $user->createToken('iPad');

    $this->withToken($current->plainTextToken)
        ->postJson(route('api.logout'))
        ->assertNoContent();

    expect(PersonalAccessToken::find($current->accessToken->id))->toBeNull()
        ->and(PersonalAccessToken::find($other->accessToken->id))->not->toBeNull();

    $this->app['auth']->forgetGuards();

    $this->withToken($current->plainTextToken)
        ->getJson(route('api.user'))
        ->assertUnauthorized();
});

test('session authenticated requests cannot log out through the api', function () {
    $this->actingAs(User::factory()->create())
        ->postJson(route('api.logout'))
        ->assertBadRequest();
});
