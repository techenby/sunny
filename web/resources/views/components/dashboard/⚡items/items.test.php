<?php

use App\Models\Item;
use App\Models\User;
use Livewire\Livewire;

test('it shows the team\'s most recently added items with where they are', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Garage', 'created_at' => now()->subDays(10)]);

    foreach (range(1, 5) as $day) {
        Item::factory()->for($user->currentTeam)->childOf($garage)->create(['name' => "Item {$day}", 'created_at' => now()->subDays($day)]);
    }
    Item::factory()->create(['name' => 'Someone else']);

    $component = Livewire::actingAs($user)->test('dashboard.items');

    expect($component->get('items')->pluck('name')->all())
        ->toBe(['Item 1', 'Item 2', 'Item 3', 'Item 4', 'Item 5']);

    $component->assertSee(__('in :parent', ['parent' => 'Garage']))->assertDontSee('Someone else');
});

test('it does not show deleted items', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'Tossed'])->delete();

    Livewire::actingAs($user)
        ->test('dashboard.items')
        ->assertDontSee('Tossed')
        ->assertSee(__('Start your inventory'));
});
