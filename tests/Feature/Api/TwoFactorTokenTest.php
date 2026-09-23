<?php

use App\Models\User;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use PragmaRX\Google2FA\Google2FA;

function twoFactorUser(): array
{
    $secret = app(Google2FA::class)->generateSecretKey();

    $user = User::factory()->withTwoFactor()->create([
        'two_factor_secret' => encrypt($secret),
    ]);

    return [$user, $secret];
}

function requestChallenge(User $user): string
{
    return test()->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])->json('challenge');
}

test('users with two factor enabled receive a challenge instead of a token', function () {
    [$user] = twoFactorUser();

    $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])
        ->assertOk()
        ->assertJsonPath('two_factor', true)
        ->assertJsonStructure(['challenge'])
        ->assertJsonMissingPath('token');

    expect($user->tokens()->count())->toBe(0);
});

test('a valid code completes the challenge and issues a token', function () {
    Event::fake([ValidTwoFactorAuthenticationCodeProvided::class]);
    [$user, $secret] = twoFactorUser();
    $challenge = requestChallenge($user);

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])
        ->assertOk()
        ->assertJsonPath('id', $user->id)
        ->assertJsonStructure(['token', 'expires_at']);

    expect($user->tokens()->sole()->name)->toBe('iPhone');
    Event::assertDispatched(ValidTwoFactorAuthenticationCodeProvided::class);
});

test('a recovery code completes the challenge and is consumed', function () {
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'recovery_code' => 'recovery-code-1',
    ])
        ->assertOk()
        ->assertJsonStructure(['token']);

    expect($user->fresh()->recoveryCodes())->not->toContain('recovery-code-1');
});

test('an invalid code is rejected', function () {
    Event::fake([TwoFactorAuthenticationFailed::class]);
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'code' => '000000',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');

    expect($user->tokens()->count())->toBe(0);
    Event::assertDispatched(TwoFactorAuthenticationFailed::class);
});

test('an invalid recovery code is rejected', function () {
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'recovery_code' => 'not-a-code',
    ])->assertUnprocessable()->assertJsonValidationErrors('recovery_code');

    expect($user->tokens()->count())->toBe(0);
});

test('a code or recovery code is required', function () {
    [$user] = twoFactorUser();

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => requestChallenge($user),
    ])->assertUnprocessable()->assertJsonValidationErrors('code');
});

test('a challenge can only be used once', function () {
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'recovery_code' => 'recovery-code-1',
    ])->assertOk();

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'recovery_code' => 'recovery-code-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('challenge');

    expect($user->tokens()->count())->toBe(1);
});

test('an expired challenge is rejected', function () {
    [$user, $secret] = twoFactorUser();
    $challenge = requestChallenge($user);

    $this->travel(6)->minutes();

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
    ])->assertUnprocessable()->assertJsonValidationErrors('challenge');
});

test('an unknown challenge is rejected', function () {
    $this->postJson(route('api.token.two-factor'), [
        'challenge' => 'made-up',
        'code' => '123456',
    ])->assertUnprocessable()->assertJsonValidationErrors('challenge');
});

test('challenge attempts are rate limited', function () {
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    foreach (range(1, 5) as $attempt) {
        $this->postJson(route('api.token.two-factor'), [
            'challenge' => $challenge,
            'code' => '000000',
        ])->assertUnprocessable();
    }

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'recovery_code' => 'recovery-code-1',
    ])->assertTooManyRequests();

    expect($user->tokens()->count())->toBe(0);
});
