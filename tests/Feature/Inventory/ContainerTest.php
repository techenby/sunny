<?php

use App\Enums\ContainerType;
use App\Models\Container;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('inventory.containers'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the containers page', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('inventory.containers'))
        ->assertOk();
});

test('renders containers for the current team only', function () {
    $user = User::factory()->withTeam()->create();
    $teamContainer = Container::factory()->for($user->currentTeam)->create(['name' => 'My Garage']);
    $otherContainer = Container::factory()->create(['name' => 'Other Garage']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->assertSee('My Garage')
        ->assertDontSee('Other Garage');
});

test('can search containers by name', function () {
    $user = User::factory()->withTeam()->create();
    Container::factory()->for($user->currentTeam)->create(['name' => 'Kitchen']);
    Container::factory()->for($user->currentTeam)->create(['name' => 'Garage']);

    $component = Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->set('search', 'Kitchen');

    expect($component->get('containers'))->toHaveCount(1);
    expect($component->get('containers')->first()->name)->toBe('Kitchen');
});

test('can sort containers', function () {
    $user = User::factory()->withTeam()->create();
    Container::factory()->for($user->currentTeam)->create(['name' => 'Bravo']);
    Container::factory()->for($user->currentTeam)->create(['name' => 'Alpha']);

    // Default sort is name asc
    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->assertSeeInOrder(['Alpha', 'Bravo'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Bravo', 'Alpha']);
});

test('can create a container', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->set('name', 'New Container')
        ->set('type', 'location')
        ->call('saveContainer')
        ->assertHasNoErrors();

    expect($user->currentTeam->containers()->where('name', 'New Container')->exists())->toBeTrue();
});

test('can create a container with parent and category', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->location()->create(['name' => 'Garage']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->set('name', 'Shelf A')
        ->set('type', 'bin')
        ->set('category', 'Storage')
        ->set('containerId', $parent->id)
        ->call('saveContainer')
        ->assertHasNoErrors();

    $child = $user->currentTeam->containers()->where('name', 'Shelf A')->first();
    expect($child)->not->toBeNull()
        ->and($child->parent_id)->toBe($parent->id)
        ->and($child->category)->toBe('Storage')
        ->and($child->type)->toBe(ContainerType::Bin);
});

test('can edit a container', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create(['name' => 'Old Name']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('editContainer', $container->id)
        ->set('name', 'New Name')
        ->call('saveContainer')
        ->assertHasNoErrors();

    expect($container->fresh()->name)->toBe('New Name');
});

test('can delete a container', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('deleteContainer', $container->id);

    expect(Container::find($container->id))->toBeNull();
});

test('deleting a container nullifies children parent_id', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->create();
    $child = Container::factory()->for($user->currentTeam)->childOf($parent)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('deleteContainer', $parent->id);

    expect($child->fresh()->parent_id)->toBeNull();
});

test('deleting a container nullifies items container_id', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create();
    $item = Item::factory()->for($user->currentTeam)->inContainer($container)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('deleteContainer', $container->id);

    expect($item->fresh()->container_id)->toBeNull();
});

test('can drill down into a container', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->create(['name' => 'Garage']);
    $child = Container::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Shelf A']);
    $sibling = Container::factory()->for($user->currentTeam)->create(['name' => 'Kitchen']);

    $component = Livewire::actingAs($user)
        ->test('pages::inventory.containers');

    // Top level shows Garage and Kitchen but not Shelf A
    $topNames = $component->get('containers')->pluck('name')->all();
    expect($topNames)->toContain('Garage')
        ->toContain('Kitchen')
        ->not->toContain('Shelf A');

    // After drilling down, shows Shelf A
    $component->call('drillDown', $parent->id);
    $childNames = $component->get('containers')->pluck('name')->all();
    expect($childNames)->toContain('Shelf A')
        ->not->toContain('Kitchen');
});

test('can navigate up from drilled-down view', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->create(['name' => 'Garage']);
    $child = Container::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Shelf A']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('drillDown', $parent->id)
        ->assertSee('Shelf A')
        ->call('navigateUp')
        ->assertSee('Garage');
});

test('cannot edit a container from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherContainer = Container::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('editContainer', $otherContainer->id);
})->throws(ModelNotFoundException::class);

test('cannot delete a container from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherContainer = Container::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('deleteContainer', $otherContainer->id);
})->throws(ModelNotFoundException::class);
