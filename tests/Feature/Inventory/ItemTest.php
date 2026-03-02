<?php

use App\Enums\ItemType;
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
    Item::factory()->location()->for($user->currentTeam)->create(['name' => 'My Garage']);
    Item::factory()->location()->create(['name' => 'Other Garage']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSee('My Garage')
        ->assertDontSee('Other Garage');
});

test('can search items by name', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()
        ->count(2)
        ->location()
        ->for($user->currentTeam)
        ->sequence(['name' => 'Kitchen'], ['name' => 'Garage'])
        ->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSeeHtml(['<span>Kitchen</span>', '<span>Garage</span>'])
        ->set('search', 'Kitchen')
        ->assertSeeHtml('<span>Kitchen</span>')
        ->assertDontSeeHtml('<span>Garage</span>');
});

test('can sort items', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()
        ->count(2)
        ->location()
        ->for($user->currentTeam)
        ->sequence(['name' => 'Kitchen'], ['name' => 'Garage'])
        ->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSeeInOrder(['Garage', 'Kitchen'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Kitchen', 'Garage']);
});

test('can create a location', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->set('form.name', 'Kitchen')
        ->set('form.type', 'location')
        ->call('save')
        ->assertHasNoErrors();

    expect(Item::firstWhere('name', 'Kitchen'))
        ->type->toBe(ItemType::Location)
        ->parent_id->toBeNull()
        ->team_id->toBe($user->current_team_id);
});

test('can create a bin', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->set('form.name', 'Toolbox')
        ->set('form.type', 'bin')
        ->call('save')
        ->assertHasNoErrors();

    expect(Item::firstWhere('name', 'Toolbox'))
        ->type->toBe(ItemType::Bin)
        ->team_id->toBe($user->current_team_id);
});

test('can create an item', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('create')
        ->set('form.name', 'Guitar')
        ->set('form.type', 'item')
        ->call('save')
        ->assertHasNoErrors();

    expect(Item::firstWhere('name', 'Guitar'))
        ->type->toBe(ItemType::Item)
        ->team_id->toBe($user->current_team_id)
        ->parent_id->toBeNull();
});

test('can create an item with a parent', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('create')
        ->set('form.name', 'Hammer')
        ->set('form.type', 'item')
        ->set('form.parent_id', $parent->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Item::firstWhere('name', 'Hammer'))
        ->parent_id->toBe($parent->id)
        ->type->toBe(ItemType::Item)
        ->team_id->toBe($user->current_team_id);
});

test('can create a container with parent', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('create')
        ->set('form.name', 'Toolbox')
        ->set('form.type', 'bin')
        ->set('form.parent_id', $parent->id)
        ->call('save')
        ->assertHasNoErrors();

    expect(Item::firstWhere('name', 'Toolbox'))
        ->parent_id->toBe($parent->id)
        ->type->toBe(ItemType::Bin)
        ->team_id->toBe($user->current_team_id);
});

test('can edit an item', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('edit', $item->id)
        ->assertSet('form.name', 'Guitar')
        ->set('form.name', 'Yamaha Guitar')
        ->call('save')
        ->assertHasNoErrors();

    expect($item->fresh()->name)->toBe('Yamaha Guitar');
});

test('can delete an item', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('delete', $item->id);

    expect($item->fresh())->toBeNull();
});

test('deleting a container nullifies children parent_id', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->location()->for($user->currentTeam)->create();
    $child = Item::factory()->bin()->childOf($parent)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('delete', $parent->id);

    expect($child->fresh()->parent_id)->toBeNull();
});

test('can drill down into a container', function () {
    $user = User::factory()->withTeam()->create();
    [$garage, $kitchen] = Item::factory()
        ->count(2)
        ->location()
        ->for($user->currentTeam)
        ->sequence(['name' => 'Garage'], ['name' => 'Kitchen'])
        ->create();
    Item::factory()->bin()->childOf($garage)->create(['name' => 'Toolbox']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSeeHtml(['<span>Garage</span>', '<span>Kitchen</span>'])
        ->assertDontSeeHtml('<span>Toolbox</span>')
        ->call('drillDown', $garage->id)
        ->assertSeeHtml('<span>Toolbox</span>')
        ->assertDontSeeHtml('<span>Kitchen</span>');
});

test('can navigate up from drilled-down view', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    Item::factory()->bin()->childOf($parent)->create(['name' => 'Toolbox']);

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->call('drillDown', $parent->id)
        ->assertSee('Toolbox')
        ->call('navigateUp')
        ->assertSee('Garage');
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

test('item count includes items from child containers', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    $child = Item::factory()->bin()->childOf($parent)->create(['name' => 'Toolbox']);

    Item::factory()->item()->childOf($parent)->create();
    Item::factory()->count(2)->item()->childOf($child)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.items')
        ->assertSeeInOrder(['Garage', '3']);
});
