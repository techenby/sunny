<?php

use App\Enums\TeamRole;
use App\Models\ItemPhoto;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('it creates a review account whose household only it belongs to', function () {
    Storage::fake();

    $this->artisan('demo:app-review', ['--password' => 'review-password'])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('review-password');

    $user = User::sole();
    $team = $user->currentTeam;

    expect($user->email)->toBe('appreview@sunnyhome.app')
        ->and(Hash::check('review-password', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->onboarded_at)->not->toBeNull()
        ->and($user->two_factor_secret)->toBeNull()
        ->and($user->teams()->count())->toBe(1)
        ->and($team->members()->count())->toBe(1)
        ->and($team->timezone)->toBe('America/Chicago')
        ->and($team->recipes()->count())->toBe(7)
        ->and($team->recipes()->whereNull('photo_path')->count())->toBe(0)
        ->and($team->items()->count())->toBeGreaterThan(30)
        ->and(ItemPhoto::count())->toBe(4)
        ->and($team->checklists()->count())->toBe(4)
        ->and($team->routines()->count())->toBe(3)
        ->and($user->deletionBlockedMessage())->toBeNull();

    Storage::assertExists(ItemPhoto::first()->path);
});

test('running it again replaces the account and its data', function () {
    Storage::fake();

    $this->artisan('demo:app-review', ['--password' => 'review-password'])->assertSuccessful();

    $first = User::sole();
    $firstTeam = $first->currentTeam;
    $firstPhoto = ItemPhoto::first()->path;

    $this->artisan('demo:app-review', ['--password' => 'review-password', '--force' => true])->assertSuccessful();

    $user = User::sole();

    expect($user->id)->not->toBe($first->id)
        ->and(Team::withTrashed()->find($firstTeam->id))->toBeNull()
        ->and(Team::count())->toBe(1)
        ->and($user->currentTeam->recipes()->count())->toBe(7)
        ->and(ItemPhoto::count())->toBe(4);

    Storage::assertMissing($firstPhoto);
});

test('it rebuilds the account after a reviewer deletes it', function () {
    Storage::fake();

    $this->artisan('demo:app-review', ['--password' => 'review-password'])->assertSuccessful();

    $this->actingAs(User::sole())
        ->deleteJson(route('api.user.destroy'), ['password' => 'review-password'])
        ->assertNoContent();

    expect(User::count())->toBe(0);

    $this->artisan('demo:app-review', ['--password' => 'review-password'])->assertSuccessful();

    expect(User::sole()->currentTeam->recipes()->count())->toBe(7);
});

test('it keeps the account when the rebuild is not confirmed', function () {
    $user = User::factory()->create(['email' => 'appreview@sunnyhome.app']);

    $this->artisan('demo:app-review', ['--password' => 'review-password'])
        ->expectsConfirmation('Delete appreview@sunnyhome.app and all of its data, then build it again?', 'no')
        ->assertFailed();

    expect(User::sole()->is($user))->toBeTrue();
});

test('it refuses to rebuild an account that owns a team other people use', function () {
    $user = User::factory()->create(['email' => 'appreview@sunnyhome.app']);
    $user->currentTeam->memberships()->create(['user_id' => User::factory()->create()->id, 'role' => TeamRole::Member]);

    $this->artisan('demo:app-review', ['--force' => true])->assertFailed();

    expect($user->fresh())->not->toBeNull()
        ->and($user->currentTeam->members()->count())->toBe(2);
});

test('it takes the password from the environment', function () {
    Storage::fake();
    config(['services.app_review.password' => 'configured-password']);

    $this->artisan('demo:app-review', ['--email' => 'review@example.com'])
        ->assertSuccessful()
        ->doesntExpectOutputToContain('configured-password');

    expect(Hash::check('configured-password', User::firstWhere('email', 'review@example.com')->password))->toBeTrue();
});

test('it generates and prints a password when none is given', function () {
    Storage::fake();

    expect(Artisan::call('demo:app-review'))->toBe(0);

    $password = Str::match('/Password[ .]+(\S+)/', Artisan::output());

    expect($password)->toHaveLength(16)
        ->and(Hash::check($password, User::sole()->password))->toBeTrue();
});
