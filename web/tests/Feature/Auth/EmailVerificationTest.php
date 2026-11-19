<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;
use Livewire\Livewire;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk()
        ->assertSee($user->email)
        ->assertSee(route('profile.edit'));
});

test('an existing account can request a verification email from the notice', function () {
    $user = User::factory()->unverified()->create();
    Notification::fake();

    $this->actingAs($user)
        ->from(route('verification.notice'))
        ->post(route('verification.send'))
        ->assertRedirect(route('verification.notice'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);
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

test('unverified users are sent to the verification screen instead of the app', function () {
    $user = User::factory()->unverified()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertRedirect(route('verification.notice'));
});

test('an open dashboard rejects Livewire updates after email verification is cleared', function () {
    $user = User::factory()->create();
    $dashboard = $this->actingAs($user)->get(route('dashboard'))->assertOk();
    $snapshot = html_entity_decode((string) str($dashboard->getContent())->betweenFirst('wire:snapshot="', '"'));

    $user->forceFill(['email_verified_at' => null])->save();

    $this->actingAs($user->fresh())
        ->postJson(Livewire::getUpdateUri(), [
            'components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]],
        ], ['X-Livewire' => 'true'])
        ->assertForbidden()
        ->assertJsonPath('message', 'Your email address is not verified.');
});

test('new users are sent a verification email when they register', function () {
    Notification::fake();

    $this->post(route('register.store'), [
        'name' => 'Nami',
        'email' => 'nami@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertSessionHasNoErrors();

    Notification::assertSentTo(User::where('email', 'nami@example.com')->sole(), VerifyEmail::class);
});

test('unverified users can still sign in and sync through the mobile API', function () {
    $user = User::factory()->unverified()->create();

    $token = $this->postJson(route('api.token'), [
        'email' => $user->email,
        'password' => 'password',
        'device_name' => 'iPhone',
    ])->assertOk()->assertJsonPath('email_verified_at', null)->json('token');

    $this->withToken($token)
        ->getJson(route('api.sync'))
        ->assertOk();
});
