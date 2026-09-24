<?php

use App\Models\User;
use App\Support\TwoFactorChallenge;
use Illuminate\Support\Facades\Event;
use Laravel\Fortify\Events\TwoFactorAuthenticationFailed;
use Laravel\Fortify\Events\ValidTwoFactorAuthenticationCodeProvided;
use PragmaRX\Google2FA\Google2FA;

function twoFactorUser(): array
{
    $secret = resolve(Google2FA::class)->generateSecretKey();

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
        'code' => resolve(Google2FA::class)->getCurrentOtp($secret),
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
        'code' => resolve(Google2FA::class)->getCurrentOtp($secret),
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

test('the challenge is rejected if two factor was disabled after it was issued', function () {
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    $user->forceFill([
        'two_factor_secret' => null,
        'two_factor_recovery_codes' => null,
        'two_factor_confirmed_at' => null,
    ])->save();

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'code' => '123456',
    ])->assertUnprocessable()->assertJsonValidationErrors('challenge');

    expect($user->tokens()->count())->toBe(0);
});

test('the challenge rate limit applies across ip addresses', function () {
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"])
            ->postJson(route('api.token.two-factor'), [
                'challenge' => $challenge,
                'code' => '000000',
            ])->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99'])
        ->postJson(route('api.token.two-factor'), [
            'challenge' => $challenge,
            'recovery_code' => 'recovery-code-1',
        ])->assertTooManyRequests();
});

test('the challenge rate limit applies across challenges for the same user', function () {
    [$user] = twoFactorUser();

    foreach (range(1, 5) as $attempt) {
        $this->withServerVariables(['REMOTE_ADDR' => "10.0.0.{$attempt}"]);

        $this->postJson(route('api.token.two-factor'), [
            'challenge' => requestChallenge($user),
            'code' => '000000',
        ])->assertUnprocessable();
    }

    $this->withServerVariables(['REMOTE_ADDR' => '10.0.0.99']);

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => requestChallenge($user),
        'recovery_code' => 'recovery-code-1',
    ])->assertTooManyRequests();
});

test('a challenge being completed by another request is rejected', function () {
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    $lock = TwoFactorChallenge::lock($challenge);
    $lock->acquire();

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'recovery_code' => 'recovery-code-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('challenge');

    expect($user->tokens()->count())->toBe(0)
        ->and($user->fresh()->recoveryCodes())->toContain('recovery-code-1');

    $lock->release();
});

test('a challenge can be retried after an invalid code', function () {
    [$user] = twoFactorUser();
    $challenge = requestChallenge($user);

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'code' => '000000',
    ])->assertUnprocessable()->assertJsonValidationErrors('code');

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => $challenge,
        'recovery_code' => 'recovery-code-1',
    ])->assertOk()->assertJsonStructure(['token']);
});

test('sending both a code and a recovery code is rejected', function () {
    [$user, $secret] = twoFactorUser();

    $this->postJson(route('api.token.two-factor'), [
        'challenge' => requestChallenge($user),
        'code' => app(Google2FA::class)->getCurrentOtp($secret),
        'recovery_code' => 'recovery-code-1',
    ])->assertUnprocessable()->assertJsonValidationErrors('recovery_code');

    expect($user->tokens()->count())->toBe(0)
        ->and($user->fresh()->recoveryCodes())->toContain('recovery-code-1');
});

test('a stored challenge can be found until it is forgotten or expires', function () {
    $user = User::factory()->create();
    $challenge = TwoFactorChallenge::issue($user, 'iPhone');

    expect(TwoFactorChallenge::find($challenge))
        ->userId->toBe($user->id)
        ->deviceName->toBe('iPhone')
        ->and(TwoFactorChallenge::find('unknown'))->toBeNull();

    TwoFactorChallenge::forget($challenge);

    expect(TwoFactorChallenge::find($challenge))->toBeNull();

    $expiring = TwoFactorChallenge::issue($user, 'iPhone');
    $this->travel(6)->minutes();

    expect(TwoFactorChallenge::find($expiring))->toBeNull();
});
