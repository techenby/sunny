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
    Item::factory()->for($user->currentTeam)->create(['name' => 'Brown Hammer']);
    Item::factory()->create(['name' => 'Pink Hammer']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSee('Brown Hammer')
        ->assertDontSee('Pink Hammer');
});

test('can search items by name', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()
        ->count(2)
        ->for($user->currentTeam)
        ->sequence(['name' => 'Hammer'], ['name' => 'Screwdriver'])
        ->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->set('search', 'Hammer')
        ->assertSee('Hammer')
        ->assertDontSee('Screwdriver');
});

test('can sort items', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()->count(2)->for($user->currentTeam)->sequence(['name' => 'Bravo'], ['name' => 'Alpha'])->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSeeInOrder(['Alpha', 'Bravo'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Bravo', 'Alpha']);
});

test('can create an item without a container', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('create')
        ->set('form.name', 'Guitar')
        ->call('save')
        ->assertHasNoErrors();

    expect(Item::firstWhere('name', 'Guitar'))->not->toBeNull()
        ->team_id->toBe($user->current_team_id)
        ->container_id->toBeNull();
});

test('can create an item with a container', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->set('form.name', 'Guidar')
        ->set('form.container_id', $container->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.name', '')
        ->assertSet('form.container_id', null);

    expect(Item::firstWhere('name', 'Guidar'))
        ->team_id->toBe($user->current_team_id)
        ->container_id->toBe($container->id);
});

test('can edit an item', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create(['name' => 'Soft Shell Case']);
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('edit', $item->id)
        ->assertSet('form.name', 'Guitar')
        ->assertSet('form.container_id', null)
        ->set('form.name', 'Yamaha Guitar')
        ->set('form.container_id', $container->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.name', '')
        ->assertSet('form.container_id', null);

    expect($item->fresh())
        ->name->toBe('Yamaha Guitar')
        ->container_id->toBe($container->id);
});

test('can delete an item', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('delete', $item->id);

    expect($item->fresh())->toBeNull();
});

test('cannot edit an item from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherItem = Item::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('edit', $otherItem->id);
})->throws(ModelNotFoundException::class);

test('cannot delete an item from another team', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('delete', $item->id);
})->throws(ModelNotFoundException::class);

test('shows container path for items', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->create(['name' => 'Garage']);
    $child = Container::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Toolbox']);
    Item::factory()->for($user->currentTeam)->inContainer($child)->create(['name' => 'Hammer']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSee('Garage / Toolbox');
});

test('can filter items to show only unassigned', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create(['name' => 'Garage']);

    Item::factory()->for($user->currentTeam)->inContainer($container)->create(['name' => 'Hammer']);
    Item::factory()->for($user->currentTeam)->create(['name' => 'Loose Screw']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSee(['Hammer', 'Loose Screw'])
        ->set('unassigned', true)
        ->assertSee('Loose Screw')
        ->assertDontSee('Hammer')
        ->set('unassigned', false)
        ->assertSee(['Hammer', 'Loose Screw']);
});

test('can filter items by container', function () {
    $user = User::factory()->withTeam()->create();
    [$garage, $kitchen] = Container::factory()->count(2)->for($user->currentTeam)->sequence(['name' => 'Garage'], ['name' => 'Kitchen'])->create();

    Item::factory()->for($user->currentTeam)->inContainer($garage)->create(['name' => 'Hammer']);
    Item::factory()->for($user->currentTeam)->inContainer($kitchen)->create(['name' => 'Spatula']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSee(['Hammer', 'Spatula'])
        ->set('containerId', $garage->id)
        ->assertSee('Hammer')
        ->assertDontSee('Spatula')
        ->set('containerId', null)
        ->assertSee(['Hammer', 'Spatula']);
});
