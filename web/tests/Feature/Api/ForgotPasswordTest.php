<?php

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;

test('a reset link is sent to an existing user', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson(route('api.password.email'), ['email' => $user->email])
        ->assertAccepted()
        ->assertExactJson(['message' => 'If that email has an account, we’ve sent it a link to reset the password.']);

    Notification::assertSentTo(
        $user,
        ResetPassword::class,
        fn (ResetPassword $notification): bool => $notification->toMail($user)->actionUrl === route('password.reset', ['token' => $notification->token, 'email' => $user->email]),
    );
});

test('emails are matched case-insensitively', function () {
    Notification::fake();
    $user = User::factory()->create(['email' => 'nami@example.com']);

    $this->postJson(route('api.password.email'), ['email' => 'Nami@Example.com'])->assertAccepted();

    Notification::assertSentTo($user, ResetPassword::class);
});

test('an unknown email gets the same response and no email', function () {
    Notification::fake();

    $this->postJson(route('api.password.email'), ['email' => 'nobody@example.com'])
        ->assertAccepted()
        ->assertExactJson(['message' => 'If that email has an account, we’ve sent it a link to reset the password.']);

    Notification::assertNothingSent();
});

test('a repeat request inside the broker throttle gets the same response without another email', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->postJson(route('api.password.email'), ['email' => $user->email])->assertAccepted();

    $this->postJson(route('api.password.email'), ['email' => $user->email])
        ->assertAccepted()
        ->assertExactJson(['message' => 'If that email has an account, we’ve sent it a link to reset the password.']);

    Notification::assertSentToTimes($user, ResetPassword::class, 1);
});

test('the email is validated', function (mixed $email) {
    Notification::fake();

    $this->postJson(route('api.password.email'), ['email' => $email])
        ->assertUnprocessable()
        ->assertOnlyJsonValidationErrors('email');

    Notification::assertNothingSent();
})->with([
    'missing' => [''],
    'invalid' => ['not-an-email'],
]);

test('reset link requests are rate limited', function () {
    Notification::fake();
    $user = User::factory()->create();

    foreach (range(1, 6) as $attempt) {
        $this->postJson(route('api.password.email'), ['email' => "person{$attempt}@example.com"])->assertAccepted();
    }

    $this->postJson(route('api.password.email'), ['email' => $user->email])->assertTooManyRequests();

    Notification::assertNothingSent();
});

test('reset links are unavailable when password resets are turned off', function () {
    Notification::fake();
    config(['fortify.features' => array_values(array_diff(config('fortify.features'), [Features::resetPasswords()]))]);
    $user = User::factory()->create();

    $this->postJson(route('api.password.email'), ['email' => $user->email])->assertNotFound();

    Notification::assertNothingSent();
});
