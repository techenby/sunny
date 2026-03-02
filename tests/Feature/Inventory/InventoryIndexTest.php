<?php

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

    Item::factory()->count(2)->location()->for($team)->create();
    Item::factory()->count(1)->bin()->for($team)->create();
    Item::factory()->count(5)->item()->for($team)->create();
    $parent = Item::factory()->location()->for($team)->create();
    Item::factory()->count(2)->item()->childOf($parent)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSee('4') // 2 locations + 1 bin + 1 parent location
        ->assertSee('7') // 5 + 2
        ->assertSee('5'); // 5 unassigned (no parent)
});
