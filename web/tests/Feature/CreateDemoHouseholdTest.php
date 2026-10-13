<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

test('it creates a demo account with a filled household', function () {
    Storage::fake();

    $this->artisan('demo:household', ['email' => 'demo@example.com', '--password' => 'secret-password'])
        ->assertSuccessful();

    $user = User::firstWhere('email', 'demo@example.com');
    $team = $user->currentTeam;

    expect(Hash::check('secret-password', $user->password))->toBeTrue()
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($team->name)->toBe('The Rivera Family')
        ->and($team->members()->count())->toBe(4)
        ->and($team->recipes()->count())->toBe(7)
        ->and($team->recipes()->whereNull('photo_path')->count())->toBe(0)
        ->and($team->items()->count())->toBeGreaterThan(30)
        ->and($team->checklists()->count())->toBe(6)
        ->and($team->routines()->count())->toBe(6)
        ->and(User::where('email', 'demo+maya@example.com')->exists())->toBeTrue();

    Storage::assertExists($team->recipes()->first()->photo_path);
});

test('it refuses an email that is already taken', function () {
    User::factory()->create(['email' => 'demo@example.com']);

    $this->artisan('demo:household', ['email' => 'demo@example.com', '--password' => 'secret-password'])
        ->assertFailed();

    expect(User::count())->toBe(1);
});
