<?php

use App\Livewire\Pages\Inventory\Bins;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Thing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/inventory/bins')
        ->assertOk()
        ->assertSee('Bins');
});

test('can view component', function () {
    Bin::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Beach Box'],
            ['name' => 'Sheets'],
            ['name' => 'Winter Bin'],
        ))
        ->create();

    Livewire::test(Bins::class)
        ->assertSee('Bins')
        ->assertSee(['Beach Box', 'Sheets', 'Winter Bin']);
});

test('can sort bins', function () {
    Bin::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Beach Box'],
            ['name' => 'Winter Bin'],
            ['name' => 'Sheets'],
        ))
        ->create();

    Livewire::test(Bins::class)
        // assert names are in creation order
        ->assertSeeInOrder(['Beach Box', 'Winter Bin', 'Sheets'])
        ->call('sort', 'name')
        // assert names are in descending order
        ->assertSeeInOrder(['Winter Bin', 'Sheets', 'Beach Box'])
        ->call('sort', 'name')
        // assert names are in ascending order
        ->assertSeeInOrder(['Beach Box', 'Sheets', 'Winter Bin'])
        ->call('sort', 'name')
        // assert names are back in default order
        ->assertSeeInOrder(['Beach Box', 'Winter Bin', 'Sheets']);
});

test('can search bins', function () {
    Bin::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Beach Box'],
            ['name' => 'Winter Bin'],
            ['name' => 'Sheets'],
        ))
        ->create();

    Livewire::test(Bins::class)
        ->assertSee(['Beach Box', 'Winter Bin', 'Sheets'])
        ->set('search', 'B')
        ->assertSee(['Beach Box', 'Winter Bin'])
        ->set('search', 'Bea')
        ->assertSee(['Beach Box']);
});

test('can adjust number per page bins', function () {
    Bin::factory()
        ->count(10)
        ->sequence(fn (Sequence $sequence) => ['name' => 'Name ' . $sequence->index + 1])
        ->create();

    Livewire::test(Bins::class)
        ->assertSee(['Name 1', 'Name 2', 'Name 3', 'Name 4', 'Name 5', 'Name 6', 'Name 7', 'Name 8', 'Name 9', 'Name 10'])
        ->set('perPage', 5)
        ->assertSee(['Name 1', 'Name 2', 'Name 3', 'Name 4', 'Name 5']);
});

test('can create bin', function () {
    $location = Location::factory()->create();

    Livewire::test(Bins::class)
        ->assertSee('Create')
        ->set('name', 'Beach Box')
        ->set('location_id', $location->id)
        ->set('type', 'Medium box with Purple Lid')
        ->call('save')
        ->assertSet('name', '')
        ->assertSet('location_id', '')
        ->assertSet('type', '');

    $this->assertDatabaseHas('bins', [
        'name' => 'Beach Box',
        'location_id' => $location->id,
        'type' => 'Medium box with Purple Lid',
    ]);
});

test('can edit bin', function () {
    [$locationA, $locationB] = Location::factory()->count(2)->create();
    $bin = Bin::factory()->create([
        'name' => 'Beach Box',
        'location_id' => $locationA->id,
        'type' => 'Medium box with Purple Lid',
    ]);

    Livewire::test(Bins::class)
        ->assertSee('Beach Box')
        ->call('edit', $bin->id)
        ->assertSet('name', 'Beach Box')
        ->assertSet('location_id', $locationA->id)
        ->assertSet('type', 'Medium box with Purple Lid')
        ->set('name', 'Beach Bin')
        ->set('location_id', $locationB->id)
        ->call('save')
        ->assertSet('name', '');

    tap($bin->fresh(), function ($bin) use ($locationB) {
        expect($bin->name)->toBe('Beach Bin');
        expect($bin->location_id)->toBe($locationB->id);
    });
});

test('can delete location', function () {
    $bin = Bin::factory()->create(['name' => 'Beach Box']);

    Livewire::test(Bins::class)
        ->assertSee('Beach Box')
        ->call('delete', $bin->id)
        ->assertDontSee('Beach Box');

    $this->assertDatabaseMissing('locations', [
        'name' => 'Beach Box',
    ]);
});

test('deleting bin updates things', function () {
    $bin = Bin::factory()->create(['name' => 'Sheets']);
    $thing = Thing::factory()->for($bin)->create(['name' => 'Light Blue King Sheets']);

    Livewire::test(Bins::class)
        ->call('delete', $bin->id);

    expect($thing->fresh()->bin_id)->toBeNull();
});
