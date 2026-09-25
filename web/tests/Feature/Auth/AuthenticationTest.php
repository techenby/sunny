<?php

use App\Models\User;
use Carbon\CarbonInterval;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Sleep;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('dashboard', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('failed logins are padded to the same duration whether or not the email exists', function (string $email) {
    User::factory()->create(['email' => 'person@example.com']);

    $this->post(route('login.store'), [
        'email' => $email,
        'password' => 'wrong-password',
    ])->assertSessionHasErrors(['email' => trans('auth.failed')]);

    $this->assertGuest();
    Sleep::assertSlept(fn (CarbonInterval $duration): bool => $duration->totalMicroseconds > 0);
})->with([
    'unknown email' => ['nobody@example.com'],
    'wrong password' => ['person@example.com'],
]);

test('successful logins are not padded', function () {
    $user = User::factory()->create();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    $this->assertAuthenticated();
    Sleep::assertNeverSlept();
});

test('outdated password hashes are rehashed on login', function () {
    $user = User::factory()->create();
    $outdatedHash = Hash::make('password', ['rounds' => 5]);
    DB::table('users')->where('id', $user->id)->update(['password' => $outdatedHash]);

    expect(Hash::needsRehash($user->fresh()->password))->toBeTrue();

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ])->assertSessionHasNoErrors();

    expect(Hash::needsRehash($user->fresh()->password))->toBeFalse();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    if (! Features::canManageTwoFactorAuthentication()) {
        $this->markTestSkipped('Two-factor authentication is not enabled.');
    }

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
