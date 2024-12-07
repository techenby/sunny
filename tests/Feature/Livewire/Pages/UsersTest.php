<?php

use App\Livewire\Pages\Users;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/users')
        ->assertOk()
        ->assertSee('Users');
});

test('can view component', function () {
    Livewire::test(Users::class)
        ->assertSee('Users');
});

test('can sort columns', function () {
    User::factory()
        ->count(4)
        ->state(new Sequence(
            ['name' => 'Ashar'],
            ['name' => 'Velvet'],
            ['name' => 'Andy'],
            ['name' => 'Geo'],
        ))
        ->create();

    Livewire::test(Users::class)
        // assert names are in creation order
        ->assertSeeInOrder(['Ashar', 'Velvet', 'Andy', 'Geo'])
        ->call('sort', 'name')
        // assert names are in descending order
        ->assertSeeInOrder(['Velvet', 'Geo', 'Ashar', 'Andy'])
        ->call('sort', 'name')
        // assert names are in ascending order
        ->assertSeeInOrder(['Andy', 'Ashar', 'Geo', 'Velvet'])
        ->call('sort', 'name')
        // assert names are back in default order
        ->assertSeeInOrder(['Ashar', 'Velvet', 'Andy', 'Geo']);
});

test('can edit user', function () {
    $user = User::factory()->create([
        'name' => 'Kouzuki Oden',
        'email' => 'oden@whitebeard.pirate',
    ]);

    Livewire::test(Users::class)
        ->call('edit', $user->id)
        ->assertSet('form.name', 'Kouzuki Oden')
        ->assertSet('form.email', 'oden@whitebeard.pirate')
        ->set('form.email', 'oden@rodger.pirate')
        ->call('save');

    expect($user->fresh()->email)->toBe('oden@rodger.pirate');
});

test('can delete user', function () {
    $user = User::factory()->create([
        'name' => 'Kouzuki Oden',
        'email' => 'oden@whitebeard.pirate',
    ]);

    Livewire::test(Users::class)
        ->call('delete', $user->id);

    $this->assertDatabaseMissing('users', [
        'name' => 'Kouzuki Oden',
        'email' => 'oden@whitebeard.pirate',
    ]);
});

test('can set status', function () {
    $user = User::factory()->create([
        'name' => 'Kouzuki Oden',
        'email' => 'oden@whitebeard.pirate',
    ]);

    Livewire::test(Users::class)
        ->call('edit', $user->id)
        ->assertSet('form.name', 'Kouzuki Oden')
        ->assertSet('form.email', 'oden@whitebeard.pirate')
        ->set('form.status', 'Eating Oden')
        ->call('save');

    expect($user->fresh()->status)->toBe('Eating Oden');
});

test('can clear status', function () {
    $user = User::factory()->create([
        'name' => 'Kouzuki Oden',
        'email' => 'oden@whitebeard.pirate',
        'status' => 'Eating Oden',
    ]);

    Livewire::test(Users::class)
        ->call('edit', $user->id)
        ->assertSet('form.name', 'Kouzuki Oden')
        ->assertSet('form.email', 'oden@whitebeard.pirate')
        ->set('form.status', '')
        ->call('save');

    expect($user->fresh()->status)->toBeNull();
});

test('can change status', function () {
    $user = User::factory()->create([
        'name' => 'Kouzuki Oden',
        'email' => 'oden@whitebeard.pirate',
        'status' => 'Eating Oden',
    ]);

    Livewire::test(Users::class)
        ->call('edit', $user->id)
        ->assertSet('form.name', 'Kouzuki Oden')
        ->assertSet('form.email', 'oden@whitebeard.pirate')
        ->set('form.status', 'Fighting')
        ->call('save');

    expect($user->fresh()->status)->toBe('Fighting');
});

test('can save status in status list', function () {
    $user = User::factory()->create();

    Livewire::test(Users::class)
        ->call('edit', $user->id)
        ->set('form.status_list.0.emoji', '🧑🏻‍💻')
        ->set('form.status_list.0.status', 'Coding - Fun')
        ->call('save');

    tap($user->fresh(), function ($user) {
        expect($user->status_list)->toBeArray()->toHaveCount(1);
        expect($user->status_list[0]['emoji'])->toBe('🧑🏻‍💻')
            ->and($user->status_list[0]['status'])->toBe('Coding - Fun');
    });
});

test('can update status in status list', function () {
    $user = User::factory()->create([
        'status_list' => [
            ['emoji' => '🧑🏻‍💻', 'status' => 'Coding - Fun'],
        ],
    ]);

    Livewire::test(Users::class)
        ->call('edit', $user->id)
        ->assertSet('form.status_list.0.emoji', '🧑🏻‍💻')
        ->assertSet('form.status_list.0.status', 'Coding - Fun')
        ->set('form.status_list.0.status', 'Coding - Sunny')
        ->call('save');

    tap($user->fresh(), function ($user) {
        expect($user->status_list)->toBeArray()->toHaveCount(1);
        expect($user->status_list[0]['emoji'])->toBe('🧑🏻‍💻')
            ->and($user->status_list[0]['status'])->toBe('Coding - Sunny');
    });
});

test('can add status to status list', function () {
    $user = User::factory()->create();

    Livewire::test(Users::class)
        ->call('edit', $user->id)
        ->assertSet('form.status_list.0.emoji', '🙂')
        ->assertSet('form.status_list.0.status', '')
        ->call('addStatusToList')
        ->assertSet('form.status_list.1.emoji', '🙂')
        ->assertSet('form.status_list.1.status', '');
});

test('can remove status from status list', function () {
    $user = User::factory()->create([
        'status_list' => [
            ['emoji' => '🧑🏻‍💻', 'status' => 'Coding - Fun'],
            ['emoji' => '🧑🏻‍💻', 'status' => 'Coding - Work'],
            ['emoji' => '🧑🏻‍💻', 'status' => 'Coding - Sunny'],
        ],
    ]);

    Livewire::test(Users::class)
        ->call('edit', $user->id)
        ->assertSet('form.status_list.0.emoji', '🧑🏻‍💻')
        ->assertSet('form.status_list.0.status', 'Coding - Fun')
        ->assertSet('form.status_list.1.emoji', '🧑🏻‍💻')
        ->assertSet('form.status_list.1.status', 'Coding - Work')
        ->assertSet('form.status_list.2.emoji', '🧑🏻‍💻')
        ->assertSet('form.status_list.2.status', 'Coding - Sunny')
        ->call('removeStatusFromList', 1)
        ->assertSet('form.status_list.0.emoji', '🧑🏻‍💻')
        ->assertSet('form.status_list.0.status', 'Coding - Fun')
        ->assertSet('form.status_list.1.emoji', '🧑🏻‍💻')
        ->assertSet('form.status_list.1.status', 'Coding - Sunny');
});

test('api token reset on close', function () {
    $user = User::factory()->create();

    Livewire::test(Users::class)
        ->call('getToken', $user->id)
        ->call('closeApiToken')
        ->assertSet('apiToken', null);
});
