<?php

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\URL;

test('email verification screen can be rendered', function () {
    $user = User::factory()->unverified()->create();

    $response = $this->actingAs($user)->get(route('verification.notice'));

    $response->assertOk()
        ->assertSee($user->email)
        ->assertSee(route('profile.edit'));
});

test('unverified users can fix their profile and request a link before entering the web app', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $dashboard = route('dashboard', ['current_team' => $user->currentTeam]);

    $this->actingAs($user)->get($dashboard)->assertRedirect(route('verification.notice'));
    $this->get(route('profile.edit'))->assertOk()->assertSee('Delete account');
    $this->from(route('profile.edit'))
        ->post(route('verification.send'))
        ->assertRedirect(route('profile.edit'))
        ->assertSessionHas('status', 'verification-link-sent');

    Notification::assertSentTo($user, VerifyEmail::class);

    $this->get(URL::temporarySignedRoute(
        'verification.verify',
        now()->addMinutes(60),
        ['id' => $user->id, 'hash' => sha1($user->email)]
    ))->assertRedirect($dashboard);

    $this->actingAs($user->fresh())->get($dashboard)->assertOk();
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
