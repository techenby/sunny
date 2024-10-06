<?php

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Volt\Volt;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/users')
        ->assertOk()
        ->assertSee('Users');
});

test('can view component', function () {
    Volt::test('pages.users')
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

    Volt::test('pages.users')
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
