<?php

use App\Livewire\Pages\Inventory\Locations;
use App\Models\Location;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/inventory/locations')
        ->assertOk()
        ->assertSee('Locations');
});

test('can view component', function () {
    Location::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Basement'],
            ['name' => 'Living Room'],
            ['name' => 'Bedroom'],
        ))
        ->create();

    Livewire::test(Locations::class)
        ->assertSee('Locations')
        ->assertSee(['Basement', 'Living Room', 'Bedroom']);
});

test('can sort locations', function () {
    Location::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Basement'],
            ['name' => 'Living Room'],
            ['name' => 'Bedroom'],
        ))
        ->create();

    Livewire::test(Locations::class)
        // assert names are in creation order
        ->assertSeeInOrder(['Basement', 'Living Room', 'Bedroom'])
        ->call('sort', 'name')
        // assert names are in descending order
        ->assertSeeInOrder(['Living Room', 'Bedroom', 'Basement'])
        ->call('sort', 'name')
        // assert names are in ascending order
        ->assertSeeInOrder(['Basement', 'Bedroom', 'Living Room'])
        ->call('sort', 'name')
        // assert names are back in default order
        ->assertSeeInOrder(['Basement', 'Living Room', 'Bedroom']);
});

test('can create location', function () {
    Livewire::test(Locations::class)
        ->assertSee('Create')
        ->set('name', 'Kitchen')
        ->call('store')
        ->assertSet('name', '');

    $this->assertDatabaseHas('locations', [
        'name' => 'Kitchen',
    ]);
});

test('can edit location', function () {
    $location = Location::factory()->create(['name' => 'Bedroom']);

    Livewire::test(Locations::class)
        ->assertSee('Bedroom')
        ->call('edit', $location->id)
        ->assertSet('name', 'Bedroom')
        ->set('name', 'Main Bedroom')
        ->call('update')
        ->assertSet('name', '');

    expect($location->fresh()->name)->toBe('Main Bedroom');
});

test('can delete location')->todo();
test('deleting location updates bins')->todo();
test('deleting location updates things')->todo();