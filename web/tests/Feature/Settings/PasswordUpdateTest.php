<?php

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Livewire\Livewire;

test('password can be updated', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', 'wrong-password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasErrors(['current_password']);
});

test('changing the password signs out the app and other browsers but keeps this one', function () {
    $user = User::factory()->create();
    $user->createToken('iPhone');
    DB::table('sessions')->insert([
        ['id' => session()->getId(), 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
        ['id' => 'another-browser', 'user_id' => $user->id, 'payload' => '', 'last_activity' => now()->timestamp],
    ]);

    Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect($user->tokens()->count())->toBe(0)
        ->and(DB::table('sessions')->where('user_id', $user->id)->pluck('id')->all())->toBe([session()->getId()]);
});

test('password updates are limited to six attempts a minute', function () {
    $this->freezeTime();
    $user = User::factory()->create([
        'password' => Hash::make('password'),
    ]);

    foreach (range(1, 6) as $attempt) {
        Livewire::actingAs($user)
            ->test('pages::settings.security')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-password')
            ->set('password_confirmation', 'new-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password']);
    }

    Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasErrors(['current_password' => 'Too many attempts. Please try again in 60 seconds.'])
        ->assertSet('current_password', '')
        ->assertSet('password', '');

    expect(Hash::check('password', $user->refresh()->password))->toBeTrue();

    $this->travel(1)->minute();

    Livewire::actingAs($user)
        ->test('pages::settings.security')
        ->set('current_password', 'password')
        ->set('password', 'new-password')
        ->set('password_confirmation', 'new-password')
        ->call('updatePassword')
        ->assertHasNoErrors();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});
