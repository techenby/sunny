<?php

use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;

test('guests cannot change their profile or password', function () {
    $this->patchJson(route('api.user.update'), ['name' => 'Nami'])->assertUnauthorized();
    $this->putJson(route('api.user.password.update'))->assertUnauthorized();
    $this->postJson(route('api.user.verification.send'))->assertUnauthorized();
});

test('a user can change their name and email, and is sent a link to verify the new one', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.user.update'), ['name' => 'Nami', 'email' => 'nami@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nami')
        ->assertJsonPath('data.email', 'nami@example.com')
        ->assertJsonPath('data.email_verified_at', null);

    expect($user->fresh())
        ->name->toBe('Nami')
        ->email->toBe('nami@example.com')
        ->email_verified_at->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('changing only the name keeps the email verified', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.user.update'), ['name' => 'Nami', 'email' => $user->email])
        ->assertOk();

    expect($user->fresh())
        ->name->toBe('Nami')
        ->email_verified_at->not->toBeNull();
});

test('a profile change can leave out fields it does not change', function () {
    $user = User::factory()->create(['name' => 'Nami']);

    $this->actingAs($user)
        ->patchJson(route('api.user.update'), ['email' => 'nami@example.com'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Nami');
});

test('profile changes are validated like the web', function (array $payload, string $error) {
    User::factory()->create(['email' => 'taken@example.com']);
    $user = User::factory()->create(['name' => 'Nami']);

    $this->actingAs($user)
        ->patchJson(route('api.user.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect($user->fresh()->name)->toBe('Nami');
})->with([
    'blank name' => [['name' => ''], 'name'],
    'long name' => [['name' => str_repeat('a', 256)], 'name'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'email taken by someone else' => [['email' => 'taken@example.com'], 'email'],
]);

test('a user can change their password with their current password using a bearer token', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    $this->withToken($token)
        ->putJson(route('api.user.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ])
        ->assertNoContent();

    expect(Hash::check('new-password', $user->fresh()->password))->toBeTrue();
});

test('a password change needs a token', function () {
    $user = User::factory()->create();

    $this->putJson(route('api.user.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertUnauthorized();

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});

test('the password is kept when the change is invalid', function (array $payload, string $error) {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    $this->withToken($token)
        ->putJson(route('api.user.password.update'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);

    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
})->with([
    'wrong current password' => [['current_password' => 'wrong-password', 'password' => 'new-password', 'password_confirmation' => 'new-password'], 'current_password'],
    'missing current password' => [['password' => 'new-password', 'password_confirmation' => 'new-password'], 'current_password'],
    'unconfirmed' => [['current_password' => 'password', 'password' => 'new-password', 'password_confirmation' => 'other-password'], 'password'],
]);

test('password changes are throttled', function () {
    $user = User::factory()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    foreach (range(1, 6) as $attempt) {
        $this->withToken($token)->putJson(route('api.user.password.update'), ['current_password' => 'wrong-password']);
    }

    $this->withToken($token)
        ->putJson(route('api.user.password.update'), ['current_password' => 'wrong-password'])
        ->assertTooManyRequests();
});

test('an unverified user can ask for the verification email again', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->postJson(route('api.user.verification.send'))
        ->assertAccepted();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('a verified user is not sent another verification email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.user.verification.send'))
        ->assertNoContent();

    Notification::assertNothingSent();
});

test('email changes and verification resends share one limit per user', function (array $requests) {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    foreach ($requests as $attempt => $route) {
        $response = $route === 'update'
            ? $this->withToken($token)->patchJson(route('api.user.update'), ['email' => "nami{$attempt}@example.com"])
            : $this->withToken($token)->postJson(route('api.user.verification.send'));

        $response->assertSuccessful();
    }

    $this->withToken($token)
        ->patchJson(route('api.user.update'), ['email' => 'nami-last@example.com'])
        ->assertTooManyRequests();

    $this->withToken($token)
        ->postJson(route('api.user.verification.send'))
        ->assertTooManyRequests();

    expect($user->fresh()->email)->not->toBe('nami-last@example.com');
})->with([
    'alternating' => [['update', 'resend', 'update', 'resend', 'update', 'resend']],
    'email changes only' => [['update', 'update', 'update', 'update', 'update', 'update']],
    'resends only' => [['resend', 'resend', 'resend', 'resend', 'resend', 'resend']],
]);

test('the email limit is kept per user', function () {
    Notification::fake();
    $nami = User::factory()->unverified()->create();
    $zoro = User::factory()->unverified()->create();
    $namiToken = $nami->createToken('iPhone')->plainTextToken;

    foreach (range(1, 6) as $attempt) {
        $this->withToken($namiToken)->postJson(route('api.user.verification.send'))->assertAccepted();
    }

    $this->withToken($namiToken)->postJson(route('api.user.verification.send'))->assertTooManyRequests();

    $this->app['auth']->forgetGuards();

    $this->withToken($zoro->createToken('iPhone')->plainTextToken)
        ->postJson(route('api.user.verification.send'))
        ->assertAccepted();
});

test('name changes do not use up the email limit', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    foreach (range(1, 10) as $attempt) {
        $this->withToken($token)
            ->patchJson(route('api.user.update'), ['name' => "Nami {$attempt}", 'email' => $user->email])
            ->assertOk();
    }

    foreach (range(1, 6) as $attempt) {
        $this->withToken($token)->postJson(route('api.user.verification.send'))->assertAccepted();
    }

    $this->withToken($token)->patchJson(route('api.user.update'), ['name' => 'Nami'])->assertOk();
    $this->withToken($token)->postJson(route('api.user.verification.send'))->assertTooManyRequests();

    expect($user->fresh()->name)->toBe('Nami');
});

test('verification emails are capped for the day', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    foreach (range(1, User::VERIFICATION_EMAILS_PER_DAY) as $attempt) {
        $this->withToken($token)->postJson(route('api.user.verification.send'))->assertAccepted();
        $this->travel(11)->seconds();
    }

    $this->travel(2)->minutes();
    $this->withToken($token)->postJson(route('api.user.verification.send'))->assertTooManyRequests();

    $this->travel(1)->days();
    $this->withToken($token)->postJson(route('api.user.verification.send'))->assertAccepted();

    Notification::assertSentToTimes($user, VerifyEmail::class, User::VERIFICATION_EMAILS_PER_DAY + 1);
});
