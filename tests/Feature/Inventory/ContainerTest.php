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
    Container::factory()->for($user->currentTeam)->create(['name' => 'My Garage']);
    Container::factory()->create(['name' => 'Other Garage']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->assertSee('My Garage')
        ->assertDontSee('Other Garage');
});

test('can search containers by name', function () {
    $user = User::factory()->withTeam()->create();
    Container::factory()
        ->count(2)
        ->for($user->currentTeam)
        ->sequence(['name' => 'Kitchen'], ['name' => 'Garage'])
        ->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->assertSee(['Kitchen', 'Garage'])
        ->set('search', 'Kitchen')
        ->assertSeeHtml('<span>Kitchen</span>')
        ->assertDontSeeHtml('<span>Garage</span>');
});

test('can sort containers', function () {
    $user = User::factory()->withTeam()->create();
    Container::factory()
        ->count(2)
        ->for($user->currentTeam)
        ->sequence(['name' => 'Kitchen'], ['name' => 'Garage'])
        ->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->assertSeeHtmlInOrder(['<span>Garage</span>', '<span>Kitchen</span>'])
        ->call('sort', 'name')
        ->assertSeeHtmlInOrder(['<span>Kitchen</span>', '<span>Garage</span>']);
});

test('can create a container', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->set('form.name', 'Kitchen')
        ->set('form.type', 'location')
        ->call('save')
        ->assertHasNoErrors();

    expect(Container::firstWhere('name', 'Kitchen'))
        ->type->toBe(ContainerType::Location)
        ->parent_id->toBeNull()
        ->team_id->toBe($user->current_team_id);
});

test('can create a container with parent', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->location()->create(['name' => 'Garage']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('create')
        ->assertSee('Add Container')
        ->assertSet('form.name', '')
        ->assertSet('form.type', 'location')
        ->set('form.name', 'Toolbox')
        ->set('form.type', 'bin')
        ->set('form.parent_id', $parent->id)
        ->call('save')
        ->assertSet('form.name', '')
        ->assertSet('form.type', 'location')
        ->assertHasNoErrors();

    expect(Container::firstWhere('name', 'Toolbox'))
        ->not->toBeNull()
        ->parent_id->toBe($parent->id)
        ->type->toBe(ContainerType::Bin)
        ->team_id->toBe($user->current_team_id);
});

test('can edit a container', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create(['name' => 'Bedroom']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('edit', $container->id)
        ->assertSee('Edit Container')
        ->assertSet('form.name', 'Bedroom')
        ->set('form.name', 'Master Bedroom')
        ->call('save')
        ->assertHasNoErrors();

    expect($container->fresh()->name)->toBe('Master Bedroom');
});

test('can delete a container', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('delete', $container->id);

    expect($container->fresh())->toBeNull();
});

test('deleting a container nullifies children parent_id', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->create();
    $child = Container::factory()->for($user->currentTeam)->childOf($parent)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('delete', $parent->id);

    expect($child->fresh()->parent_id)->toBeNull();
});

test('deleting a container nullifies items container_id', function () {
    $user = User::factory()->withTeam()->create();
    $container = Container::factory()->for($user->currentTeam)->create();
    $item = Item::factory()->for($user->currentTeam)->inContainer($container)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('delete', $container->id);

    expect($item->fresh()->container_id)->toBeNull();
});

test('can drill down into a container', function () {
    $user = User::factory()->withTeam()->create();
    [$garage, $kitchen] = Container::factory()
        ->count(2)
        ->for($user->currentTeam)
        ->sequence(['name' => 'Garage'], ['name' => 'Kitchen'])
        ->create();
    Container::factory()->for($user->currentTeam)->childOf($garage)->create(['name' => 'Toolbox']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->assertSeeHtml(['<span>Garage</span>', '<span>Kitchen</span>'])
        ->assertDontSeeHtml('<span>Toolbox</span>')
        ->call('drillDown', $garage->id)
        ->assertSeeHtml('<span>Toolbox</span>')
        ->assertDontSeeHtml('<span>Kitchen</span>');
});

test('can navigate up from drilled-down view', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Container::factory()->for($user->currentTeam)->create(['name' => 'Garage']);
    Container::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Toolbox']);

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('drillDown', $parent->id)
        ->assertSee('Toolbox')
        ->call('navigateUp')
        ->assertSee('Garage');
});

test('cannot edit a container from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherContainer = Container::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('edit', $otherContainer->id);
})->throws(ModelNotFoundException::class);

test('cannot delete a container from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherContainer = Container::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.containers')
        ->call('delete', $otherContainer->id);
})->throws(ModelNotFoundException::class);
