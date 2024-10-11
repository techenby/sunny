<?php

use App\Livewire\Pages\Inventory\Things;
use App\Models\Bin;
use App\Models\Location;
use App\Models\Thing;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/inventory/things')
        ->assertOk()
        ->assertSee('Things');
});

test('can view component', function () {
    Thing::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Key Light'],
            ['name' => 'Stream Deck'],
            ['name' => 'Busy Box'],
        ))
        ->create();

    Livewire::test(Things::class)
        ->assertSee('Things')
        ->assertSee(['Key Light', 'Stream Deck', 'Busy Box']);
});

test('can sort things', function () {
    Thing::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Key Light'],
            ['name' => 'Stream Deck'],
            ['name' => 'Busy Box'],
        ))
        ->create();

    Livewire::test(Things::class)
        // assert names are in creation order
        ->assertSeeInOrder(['Key Light', 'Stream Deck', 'Busy Box'])
        ->call('sort', 'name')
        // assert names are in descending order
        ->assertSeeInOrder(['Stream Deck', 'Key Light', 'Busy Box'])
        ->call('sort', 'name')
        // assert names are in ascending order
        ->assertSeeInOrder(['Busy Box', 'Key Light', 'Stream Deck'])
        ->call('sort', 'name')
        // assert names are back in default order
        ->assertSeeInOrder(['Key Light', 'Stream Deck', 'Busy Box']);
});

test('can search things', function () {
    Thing::factory()
        ->count(3)
        ->state(new Sequence(
            ['name' => 'Key Light'],
            ['name' => 'Stream Deck'],
            ['name' => 'Busy Box'],
        ))
        ->create();

    Livewire::test(Things::class)
        ->assertSee(['Key Light', 'Stream Deck', 'Busy Box'])
        ->set('search', 'e')
        ->assertSee(['Key Light', 'Stream Deck'])
        ->set('search', 'eam')
        ->assertSee(['Stream Deck']);
});

test('can adjust number per page things', function () {
    Thing::factory()
        ->count(10)
        ->sequence(fn (Sequence $sequence) => ['name' => 'Name ' . $sequence->index + 1])
        ->create();

    Livewire::test(Things::class)
        ->assertSee(['Name 1', 'Name 2', 'Name 3', 'Name 4', 'Name 5', 'Name 6', 'Name 7', 'Name 8', 'Name 9', 'Name 10'])
        ->set('perPage', 5)
        ->assertSee(['Name 1', 'Name 2', 'Name 3', 'Name 4', 'Name 5']);
});

test('can create thing', function () {
    $bin = Bin::factory()->create();
    $location = Location::factory()->create();

    Livewire::test(Things::class)
        ->assertSee('Create')
        ->set('name', 'Key Light')
        ->set('bin_id', $bin->id)
        ->set('location_id', $location->id)
        ->call('save')
        ->assertSet('name', '')
        ->assertSet('bin_id', '')
        ->assertSet('location_id', '');

    $this->assertDatabaseHas('things', [
        'name' => 'Key Light',
        'bin_id' => $bin->id,
        'location_id' => $location->id,
    ]);
});

test('can edit thing', function () {
    [$binA, $binB] = Bin::factory()->count(2)->create();
    [$locationA, $locationB] = Location::factory()->count(2)->create();
    $thing = Thing::factory()->for($binA)->for($locationA)->create([
        'name' => 'Elgato Key Light',
    ]);

    Livewire::test(Things::class)
        ->assertSee('Key Light')
        ->call('edit', $thing->id)
        ->assertSet('name', 'Elgato Key Light')
        ->assertSet('bin_id', $binA->id)
        ->assertSet('location_id', $locationA->id)
        ->set('name', 'Key Light')
        ->set('bin_id', $binB->id)
        ->set('location_id', $locationB->id)
        ->call('save')
        ->assertSet('name', '');

    tap($thing->fresh(), function ($thing) use ($binB, $locationB) {
        expect($thing->name)->toBe('Key Light');
        expect($thing->bin_id)->toBe($binB->id);
        expect($thing->location_id)->toBe($locationB->id);
    });
});

test('selecting bin sets location', function () {
    $location = Location::factory()->create();
    $bin = Bin::factory()->for($location)->create();

    Livewire::test(Things::class)
        ->set('name', 'Key Light')
        ->set('bin_id', $bin->id)
        ->assertSet('location_id', $location->id);
});

test('can delete thing', function () {
    $thing = Thing::factory()->create(['name' => 'Key Light']);

    Livewire::test(Things::class)
        ->assertSee('Key Light')
        ->call('delete', $thing->id)
        ->assertDontSee('Key Light');

    $this->assertDatabaseMissing('things', [
        'name' => 'Key Light',
    ]);
});
