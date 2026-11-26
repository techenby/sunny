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

test('account deletion is limited to six attempts a minute', function () {
    $this->freezeTime();
    $user = User::factory()->create();

    foreach (range(1, 6) as $attempt) {
        Livewire::actingAs($user)
            ->test('pages::settings.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser')
            ->assertHasErrors(['password']);
    }

    Livewire::actingAs($user)
        ->test('pages::settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasErrors(['password' => 'Too many attempts. Please try again in 60 seconds.']);

    expect($user->fresh())->not->toBeNull();

    $this->travel(1)->minute();

    Livewire::actingAs($user)
        ->test('pages::settings.delete-user-form')
        ->set('password', 'password')
        ->call('deleteUser')
        ->assertHasNoErrors()
        ->assertRedirect('/');

    expect($user->fresh())->toBeNull();
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

test('the profile page is limited on its own, apart from the app', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('iPhone')->plainTextToken;

    foreach (range(1, 6) as $attempt) {
        $this->withToken($token)->postJson(route('api.user.verification.send'))->assertAccepted();
    }

    $this->withToken($token)->postJson(route('api.user.verification.send'))->assertTooManyRequests();

    $page = Livewire::actingAs($user)->test('pages::settings.profile');

    foreach (range(1, 5) as $attempt) {
        $page->call('resendVerificationNotification');
    }

    $page->set('email', 'nami1@example.com')->call('updateProfileInformation')->assertHasNoErrors();

    $page->set('email', 'nami2@example.com')
        ->call('updateProfileInformation')
        ->assertHasErrors(['email']);

    expect($user->fresh()->email)->toBe('nami1@example.com');
    Notification::assertSentToTimes($user, VerifyEmail::class, 12);
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

test('resends from the profile page are capped for the day', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $page = Livewire::actingAs($user)->test('pages::settings.profile');

    foreach (range(1, User::VERIFICATION_EMAILS_PER_DAY) as $attempt) {
        $page->call('resendVerificationNotification');
        $this->travel(11)->seconds();
    }

    $this->travel(2)->minutes();

    $page->call('resendVerificationNotification')
        ->assertDispatched('toast-show', fn (string $event, array $params): bool => ($params['slots']['text'] ?? '') === 'Too many verification emails today. Try again tomorrow.');

    Notification::assertSentToTimes($user, VerifyEmail::class, User::VERIFICATION_EMAILS_PER_DAY);
});

test('the profile page, the app and the verify page share one daily count', function () {
    Notification::fake();
    $user = User::factory()->unverified()->create();
    $token = $user->createToken('iPhone')->plainTextToken;
    $page = Livewire::actingAs($user)->test('pages::settings.profile');

    foreach (range(1, User::VERIFICATION_EMAILS_PER_DAY / 2) as $attempt) {
        $this->withToken($token)->postJson(route('api.user.verification.send'))->assertAccepted();
        $page->call('resendVerificationNotification');
        $this->travel(11)->seconds();
    }

    $this->travel(2)->minutes();

    $this->withToken($token)->postJson(route('api.user.verification.send'))->assertTooManyRequests();

    $page->call('resendVerificationNotification')
        ->assertDispatched('toast-show', fn (string $event, array $params): bool => ($params['slots']['text'] ?? '') === 'Too many verification emails today. Try again tomorrow.');

    $this->actingAs($user, 'web')->post(route('verification.send'))->assertTooManyRequests();

    Notification::assertSentToTimes($user, VerifyEmail::class, User::VERIFICATION_EMAILS_PER_DAY);
});
