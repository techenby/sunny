<?php

use App\Models\User;
use Livewire\Livewire;

test('page renders successfully', function () {
    $user = User::factory()->withTeam()->create();

    $this->actingAs($user)
        ->get(route('inventory.items'))
        ->assertOk();
});

test('component renders successfully', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items.index')
        ->assertOk();
});
