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

test('a user can change their name and email', function () {
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

test('changing the email through the API sends a verification email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.user.update'), ['email' => 'nami@example.com'])
        ->assertOk();

    Notification::assertSentTo($user, VerifyEmail::class);
});
