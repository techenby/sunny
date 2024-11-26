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
