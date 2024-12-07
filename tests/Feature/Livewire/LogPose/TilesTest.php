<?php

use App\Livewire\Pages\LogPose\Tiles;
use App\Models\Tile;
use App\Models\User;
use Livewire\Livewire;

test('can view page', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get('/log-pose/tiles')
        ->assertOk()
        ->assertSee('Tiles');
});

test('can view component', function () {
    Tile::create(['name' => 'calendar-andy']);
    Tile::create(['name' => 'calendar-family']);

    Livewire::test(Tiles::class)
        ->assertSee('Tiles')
        ->assertSee(['calendar-andy', 'calendar-family']);
});

test('can create tile', function () {
    Livewire::test(Tiles::class)
        ->assertSee('Create')
        ->set('name', 'andy')
        ->set('type', 'coworkers')
        ->set('data', '[{"name": "Andy","location": "Chicago, IL","timezone": "America/Chicago"}]')
        ->call('save')
        ->assertSet('name', '')
        ->assertSet('type', '')
        ->assertSet('data', '');

    $tile = Tile::where('name', 'andy')->where('type', 'coworkers')->first();
    expect($tile)->not->toBeNull();
    expect($tile->data)->toBeArray();
});

test('can edit tile', function () {
    $tile = Tile::factory()->create([
        'name' => 'wano',
        'type' => 'weather',
    ]);

    Livewire::test(Tiles::class)
        ->call('edit', $tile->id)
        ->assertSet('name', 'wano')
        ->assertSet('type', 'weather')
        ->set('name', 'flower-capital')
        ->call('save');

    expect($tile->fresh()->name)->toBe('flower-capital');
});

test('can delete tile', function () {
    $tile = Tile::factory()->create([
        'name' => 'oden',
        'type' => 'calendar'
    ]);

    Livewire::test(Tiles::class)
        ->call('delete', $tile->id);

    $this->assertDatabaseMissing('dashboard_tiles', [
        'name' => 'oden',
        'type' => 'calendar',
    ]);
});

test('when setting type to calendar color and links are populated', function () {
    $tile = Tile::factory()->create([
        'name' => 'wano',
        'type' => 'weather',
    ]);

    Livewire::test(Tiles::class)
        ->call('edit', $tile->id)
        ->set('type', 'calendar')
        ->assertSet('settings', ['color' => '#', 'links' => ['']]);
});
