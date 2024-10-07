<?php

use App\Livewire\Pages\Inventory\Locations;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Thing;
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
        ->call('save')
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
        ->call('save')
        ->assertSet('name', '');

    expect($location->fresh()->name)->toBe('Main Bedroom');
});

test('can delete location', function () {
    $location = Location::factory()->create(['name' => 'Bedroom']);

    Livewire::test(Locations::class)
        ->assertSee('Bedroom')
        ->call('delete', $location->id)
        ->assertDontSee('Bedroom');

    $this->assertDatabaseMissing('locations', [
        'name' => 'Bedroom',
    ]);
});

test('deleting location updates bins', function () {
    $location = Location::factory()->create(['name' => 'Bedroom']);
    $bin = Bin::factory()->for($location)->create(['name' => 'Sheets Box']);

    Livewire::test(Locations::class)
        ->call('delete', $location->id);

    expect($bin->fresh()->location_id)->toBeNull();
});

test('deleting location updates things', function () {
    $location = Location::factory()->create(['name' => 'Bedroom']);
    $thing = Thing::factory()->for($location)->create(['name' => 'Light Blue King Sheets']);

    Livewire::test(Locations::class)
        ->call('delete', $location->id);

    expect($thing->fresh()->location_id)->toBeNull();
});
