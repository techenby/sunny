<?php

use App\Models\Container;
use App\Models\Item;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('inventory.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the inventory overview', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('inventory.index'))
        ->assertOk();
});

test('overview displays correct counts', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->currentTeam;

    Container::factory()->count(3)->for($team)->create();
    Item::factory()->count(5)->for($team)->create();
    Item::factory()->count(2)->inContainer(Container::factory()->for($team)->create())->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSee('4') // 3 + 1 from inContainer
        ->assertSee('7') // 5 + 2
        ->assertSee('5'); // 5 unassigned
});
