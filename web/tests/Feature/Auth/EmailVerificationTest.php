<?php

use App\Http\Middleware\EnsureEmailIsVerifiedUnlessPairedKiosk;
use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk();
});

test('email can be verified', function () {
    $user = User::factory()->unverified()->create();
    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)
        ->get($verificationUrl)
        ->assertRedirect('/' . $user->currentTeam->slug . '/dashboard?verified=1');

    Event::assertDispatched(Verified::class);

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
});

test('email is not verified with invalid hash', function () {
    $user = User::factory()->unverified()->create();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1('wrong-email')]
    );

    $this->actingAs($user)->get($verificationUrl);

    expect($user->fresh()->hasVerifiedEmail())->toBeFalse();
});

test('already verified user visiting verification link is redirected without firing event again', function () {
    $user = User::factory()->create([
        'email_verified_at' => now(),
    ]);
    Event::fake();

    $verificationUrl = URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    );

    $this->actingAs($user)->get($verificationUrl)
        ->assertRedirect('/' . $user->currentTeam->slug . '/dashboard?verified=1');

    expect($user->fresh()->hasVerifiedEmail())->toBeTrue();
    Event::assertNotDispatched(Verified::class);
});

test('registering sends a verification email', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Nico Robin',
        'email' => 'robin@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ]);

    Notification::assertSentTo(User::where('email', 'robin@example.com')->sole(), VerifyEmail::class);
});

test('unverified users are sent to verify their email before using the app', function (string $routeName) {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route($routeName, ['current_team' => $user->currentTeam]))
        ->assertRedirect(route('verification.notice'));
})->with(['dashboard', 'teams.index', 'appearance.edit', 'kiosk.configure.settings']);

test('the verification screen shows the address and lets the user change it', function () {
    $user = User::factory()->unverified()->create(['email' => 'robin@example.com']);

    $this->actingAs($user)
        ->get(route('verification.notice'))
        ->assertSee('robin@example.com')
        ->assertSee(route('profile.edit'));
});

test('verified users can use the app', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard', ['current_team' => $user->currentTeam]))
        ->assertOk();
});

test('open pages check for a verified email on every update, not just when they load', function () {
    expect(Livewire::getPersistentMiddleware())->toContain(EnsureEmailIsVerified::class, EnsureEmailIsVerifiedUnlessPairedKiosk::class);
});
