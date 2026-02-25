<?php

use App\Models\Container;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('inventory.items'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the items page', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('inventory.items'))
        ->assertOk();
});

test('renders items for the current team only', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'My Hammer']);
    Item::factory()->create(['name' => 'Other Hammer']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSee('My Hammer')
        ->assertDontSee('Other Hammer');
});

test('can search items by name', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'Hammer']);
    Item::factory()->for($user->currentTeam)->create(['name' => 'Screwdriver']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->set('search', 'Hammer')
        ->assertSee('Hammer')
        ->assertDontSee('Screwdriver');
});

test('can sort items', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'Bravo']);
    Item::factory()->for($user->currentTeam)->create(['name' => 'Alpha']);

    // Default sort is name asc, so Alpha comes first
    $component = Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSeeInOrder(['Alpha', 'Bravo']);

    // Toggle to desc
    $component->call('sort', 'name')
        ->assertSeeInOrder(['Bravo', 'Alpha']);
});

test('can create an item without a container', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->set('name', 'New Item')
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = $user->currentTeam->items()->where('name', 'New Item')->first();
    expect($item)->not->toBeNull()
        ->and($item->container_id)->toBeNull();
});

test('can create an item with a container', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->set('name', 'New Item')
        ->set('containerId', $container->id)
        ->call('saveItem')
        ->assertHasNoErrors();

    $item = $user->currentTeam->items()->where('name', 'New Item')->first();
    expect($item->container_id)->toBe($container->id);
});

test('can edit an item', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Old Name']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('editItem', $item->id)
        ->set('name', 'New Name')
        ->call('saveItem')
        ->assertHasNoErrors();

    expect($item->fresh()->name)->toBe('New Name');
});

test('can delete an item', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('deleteItem', $item->id);

    expect(Item::find($item->id))->toBeNull();
});

test('cannot edit an item from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherItem = Item::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('editItem', $otherItem->id);
})->throws(ModelNotFoundException::class);

test('cannot delete an item from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherItem = Item::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('deleteItem', $otherItem->id);
})->throws(ModelNotFoundException::class);

test('shows container path for items', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->create(['name' => 'Garage']);
    $child = Container::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Shelf A']);
    Item::factory()->for($user->currentTeam)->inContainer($child)->create(['name' => 'Hammer']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSee('Garage / Shelf A');
});
