<?php

use App\Livewire\Pages\LogPose\Tiles;
use App\Models\User;
use Livewire\Livewire;
use Spatie\Dashboard\Models\Tile;

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
        ->set('name', 'coworkers-andy')
        ->set('data', "[['name'=>'Andy','location'=>'Chicago, IL','timezone'=>'America/Chicago']]")
        ->call('save')
        ->assertSet('name', '')
        ->assertSet('data', '[]');

    $this->assertDatabaseHas('dashboard_tiles', [
        'name' => 'coworkers-andy',
    ]);
});
