<?php

use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Laravel\Fortify\Features;
use Laravel\Sanctum\PersonalAccessToken;

test('new users can register and are signed in straight away', function () {
    $this->freezeSecond();
    Notification::fake();

    $response = $this->postJson(route('api.register'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
    ])
        ->assertCreated()
        ->assertJsonPath('name', 'Nami')
        ->assertJsonPath('email', 'nami@example.com')
        ->assertJsonPath('email_verified_at', null)
        ->assertJsonPath('two_factor_enabled', false)
        ->assertJsonPath('expires_at', now()->addDays(30)->toIso8601String())
        ->assertJsonStructure(['id', 'email_verified_at', 'current_team_id', 'onboarded_at', 'token']);

    $user = User::where('email', 'nami@example.com')->sole();
    $token = PersonalAccessToken::findToken($response->json('token'));
    Notification::assertSentTo($user, VerifyEmail::class);

    expect($response->json('id'))->toBe($user->id)
        ->and($response->json('current_team_id'))->toBe($user->personalTeam()->id)
        ->and($token->tokenable->is($user))->toBeTrue()
        ->and($token->name)->toBe('iPhone')
        ->and($token->abilities)->toBe(['*']);

    $this->withToken($response->json('token'))
        ->getJson(route('api.user'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

test('new users start out needing onboarding', function () {
    $this->postJson(route('api.register'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
    ])->assertCreated()->assertJsonPath('onboarded_at', null);

    expect(User::where('email', 'nami@example.com')->sole()->needsOnboarding())->toBeTrue();
});

test('people who register with a pending invitation skip onboarding', function () {
    $luffy = User::factory()->create();
    TeamInvitation::factory()->for($luffy->currentTeam)->create(['email' => 'Zoro@Example.com']);

    $response = $this->postJson(route('api.register'), [
        'name' => 'Roronoa Zoro',
        'email' => 'zoro@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
    ])->assertCreated();

    expect($response->json('onboarded_at'))->not->toBeNull()
        ->and(User::where('email', 'zoro@example.com')->sole()->needsOnboarding())->toBeFalse();
});

test('registering fires the registered event', function () {
    Event::fake([Registered::class]);

    $this->postJson(route('api.register'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
    ])->assertCreated();

    Event::assertDispatched(fn (Registered $event): bool => $event->user->email === 'nami@example.com');
});

test('emails are stored in lowercase', function () {
    $this->postJson(route('api.register'), [
        'name' => 'Nami',
        'email' => 'Nami@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
    ])->assertCreated()->assertJsonPath('email', 'nami@example.com');
});

test('registration is validated', function (array $overrides, string $field) {
    $this->postJson(route('api.register'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
        ...$overrides,
    ])->assertUnprocessable()->assertOnlyJsonValidationErrors($field);

    expect(User::count())->toBe(0)
        ->and(PersonalAccessToken::count())->toBe(0);
})->with([
    'missing name' => [['name' => ''], 'name'],
    'missing email' => [['email' => ''], 'email'],
    'invalid email' => [['email' => 'not-an-email'], 'email'],
    'missing password' => [['password' => '', 'password_confirmation' => ''], 'password'],
    'mismatched confirmation' => [['password_confirmation' => 'different'], 'password'],
    'missing device name' => [['device_name' => ''], 'device_name'],
]);

test('an email that already has an account cannot register again', function () {
    $user = User::factory()->create(['email' => 'nami@example.com']);

    $this->postJson(route('api.register'), [
        'name' => 'Nami',
        'email' => 'Nami@Example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
    ])->assertUnprocessable()->assertJsonValidationErrors('email');

    expect(User::count())->toBe(1)
        ->and($user->tokens()->count())->toBe(0);
});

test('registration is rate limited', function () {
    foreach (range(1, 6) as $attempt) {
        $this->postJson(route('api.register'), ['device_name' => 'iPhone'])->assertUnprocessable();
    }

    $this->postJson(route('api.register'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
    ])->assertTooManyRequests();

    expect(User::count())->toBe(0);
});

test('registration is unavailable when it is turned off', function () {
    config(['fortify.features' => array_values(array_diff(config('fortify.features'), [Features::registration()]))]);

    $this->postJson(route('api.register'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
        'device_name' => 'iPhone',
    ])->assertNotFound();

    expect(User::count())->toBe(0);
});
