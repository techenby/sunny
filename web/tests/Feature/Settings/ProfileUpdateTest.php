<?php

use App\Enums\TeamRole;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

test('profile page is displayed', function () {
    $this->actingAs(User::factory()->create())
        ->get(route('profile.edit'))
        ->assertOk();
});

test('profile information can be updated', function () {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', 'test@example.com')
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh())
        ->name->toEqual('Test User')
        ->email->toEqual('test@example.com')
        ->email_verified_at->toBeNull();

    Notification::assertSentTo($user, VerifyEmail::class);
});

test('email verification status is unchanged when email address is unchanged', function () {
    Notification::fake();
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.profile')
        ->set('name', 'Test User')
        ->set('email', $user->email)
        ->call('updateProfileInformation')
        ->assertHasNoErrors();

    expect($user->fresh()->email_verified_at)->not->toBeNull();

    Notification::assertNothingSent();
});

test('an unverified user can still change their email and delete their account', function () {
    $this->actingAs(User::factory()->unverified()->create())
        ->get(route('profile.edit'))
        ->assertOk()
        ->assertSeeLivewire('pages::settings.delete-user-form');
});

test('user can delete their account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull()
        ->and(auth()->check())->toBeFalse();
});

test('correct password must be provided to delete account', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::settings.delete-user-form')
        ->set('password', 'wrong-password')
        ->call('deleteUser')
        ->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('account deletion is blocked while the user owns a team other people use', function () {
    $user = User::factory()->create();
    $user->currentTeam->update(['name' => 'Straw Hats']);
    $user->currentTeam->memberships()->create(['user_id' => User::factory()->create()->id, 'role' => TeamRole::Member]);

    Livewire::actingAs($user)
        ->test('pages::settings.delete-user-form')
        ->assertSee('Other people still use Straw Hats')
        ->assertSee('Manage teams')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['password']);

    expect($user->fresh())->not->toBeNull();
});

test('email changes and resends on the profile page share one limit with the app', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    foreach (range(1, 3) as $attempt) {
        $this->withToken($token)->postJson(route('api.user.verification.send'))->assertAccepted();
    }

    $page = Livewire::actingAs($user)->test('pages::settings.profile');
    $page->set('email', 'nami1@example.com')->call('updateProfileInformation')->assertHasNoErrors();
    $page->call('resendVerificationNotification');
    $page->set('email', 'nami2@example.com')->call('updateProfileInformation')->assertHasNoErrors();

    $page->set('email', 'nami3@example.com')
        ->call('updateProfileInformation')
        ->assertHasErrors(['email']);

    $page->call('resendVerificationNotification');

    $this->app['auth']->forgetGuards();
    $this->withToken($token)->postJson(route('api.user.verification.send'))->assertTooManyRequests();

    expect($user->fresh()->email)->toBe('nami2@example.com');
    Notification::assertSentToTimes($user, VerifyEmail::class, 6);
});

test('a limited resend on the profile page says so', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $page = Livewire::actingAs($user)->test('pages::settings.profile');

    foreach (range(1, 6) as $attempt) {
        $page->call('resendVerificationNotification');
    }

    $page->call('resendVerificationNotification')
        ->assertDispatched('toast-show', fn (string $event, array $params): bool => str_starts_with($params['slots']['text'] ?? '', 'Too many verification emails.'));

    Notification::assertSentToTimes($user, VerifyEmail::class, 6);
});

test('name changes on the profile page do not use up the email limit', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $page = Livewire::actingAs($user)->test('pages::settings.profile');

    foreach (range(1, 10) as $attempt) {
        $page->set('name', "Nami {$attempt}")->call('updateProfileInformation')->assertHasNoErrors();
    }

    foreach (range(1, 6) as $attempt) {
        $page->set('email', "nami{$attempt}@example.com")->call('updateProfileInformation')->assertHasNoErrors();
    }

    $page->set('email', 'nami-last@example.com')->call('updateProfileInformation')->assertHasErrors(['email']);

    expect($user->fresh())->name->toBe('Nami 10')->email->toBe('nami6@example.com');
});
