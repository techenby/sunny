<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('inventory.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the items page', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('inventory.index'))
        ->assertOk();
});

test('renders items for the current team only', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'Brown Hammer']);
    Item::factory()->create(['name' => 'Pink Hammer']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSee('Brown Hammer')
        ->assertDontSee('Pink Hammer');
});

test('can search items by name', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()
        ->count(2)
        ->for($user->currentTeam)
        ->sequence(
            ['name' => 'Hammer', 'parent_id' => null],
            ['name' => 'Screwdriver', 'parent_id' => null]
        )
        ->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSeeHtml('<span>Hammer</span>')
        ->assertSeeHtml('<span>Screwdriver</span>')
        ->set('search', 'Hammer')
        ->assertSeeHtml('<span>Hammer</span>')
        ->assertDontSeeHtml('<span>Screwdriver</span>');
});

test('can sort items', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()->count(2)->for($user->currentTeam)->sequence(['name' => 'Bravo'], ['name' => 'Alpha'])->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSeeInOrder(['Alpha', 'Bravo'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Bravo', 'Alpha']);
});

test('can create an item without a parent', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->set('form.name', 'Guitar')
        ->set('form.type', ItemType::Item)
        ->call('save')
        ->assertHasNoErrors();

    expect(Item::firstWhere('name', 'Guitar'))->not->toBeNull()
        ->team_id->toBe($user->current_team_id)
        ->parent_id->toBeNull();
});

test('can create an item with a parent', function () {
    $user = User::factory()->withTeam()->create();
    $location = Item::factory()->for($user->currentTeam)->create(['name' => 'Bedroom']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->set('form.name', 'Guitar')
        ->set('form.type', 'item')
        ->set('form.parent_id', $location->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.name', '')
        ->set('form.type', null)
        ->assertSet('form.parent_id', null);

    expect(Item::firstWhere('name', 'Guitar'))
        ->team_id->toBe($user->current_team_id)
        ->parent_id->toBe($location->id);
});

test('can edit an item', function () {
    $user = User::factory()->withTeam()->create();
    $bin = Item::factory()->for($user->currentTeam)->create(['name' => 'Soft Shell Case', 'type' => ItemType::Bin]);
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('edit', $item->id)
        ->assertSet('form.name', 'Guitar')
        ->assertSet('form.parent_id', null)
        ->set('form.name', 'Yamaha Guitar')
        ->set('form.parent_id', $bin->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.name', '')
        ->assertSet('form.parent_id', null);

    expect($item->fresh())
        ->name->toBe('Yamaha Guitar')
        ->parent_id->toBe($bin->id);
});

test('can delete an item', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('delete', $item->id);

    expect($item->fresh())->toBeNull();
});

test('cannot edit an item from another team', function () {
    $user = User::factory()->withTeam()->create();
    $otherItem = Item::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('edit', $otherItem->id);
})->throws(ModelNotFoundException::class);

test('cannot delete an item from another team', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('delete', $item->id);
})->throws(ModelNotFoundException::class);

test('can navigate down into a child item', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
    Item::factory()->for($user->currentTeam)->bin()->childOf($parent)->create(['name' => 'Closet']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSeeHtml('<span>Bedroom</span>')
        ->assertDontSeeHtml('<span>Closet</span>')
        ->call('navigateDown', $parent->id)
        ->assertSeeHtml('<span>Closet</span>')
        ->assertSet('parentId', $parent->id);
});

test('can navigate up from a child item', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
    Item::factory()->for($user->currentTeam)->bin()->childOf($parent)->create(['name' => 'Closet']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('navigateDown', $parent->id)
        ->assertSee('Closet')
        ->call('navigateUp')
        ->assertSee('Bedroom');
});

test('breadcrumbs show full ancestor path', function () {
    $user = User::factory()->withTeam()->create();
    $bedroom = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
    $closet = Item::factory()->for($user->currentTeam)->bin()->childOf($bedroom)->create(['name' => 'Right Closet']);
    $tote = Item::factory()->for($user->currentTeam)->bin()->childOf($closet)->create(['name' => 'Game Tote']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('navigateDown', $bedroom->id)
        ->call('navigateDown', $closet->id)
        ->call('navigateDown', $tote->id)
        ->assertSeeInOrder(['All', 'Bedroom', 'Right Closet', 'Game Tote']);
});

test('breadcrumbs are empty at root level', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSee('All')
        ->assertSet('parentId', null);
});

test('clicking a breadcrumb navigates to that level', function () {
    $user = User::factory()->withTeam()->create();
    $bedroom = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
    $closet = Item::factory()->for($user->currentTeam)->bin()->childOf($bedroom)->create(['name' => 'Right Closet']);
    Item::factory()->for($user->currentTeam)->bin()->childOf($closet)->create(['name' => 'Game Tote']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('navigateDown', $bedroom->id)
        ->call('navigateDown', $closet->id)
        ->assertSeeHtml('<span>Game Tote</span>')
        ->call('navigateDown', $bedroom->id)
        ->assertSet('parentId', $bedroom->id)
        ->assertSeeHtml('<span>Right Closet</span>')
        ->assertDontSeeHtml('<span>Game Tote</span>');
});

test('create pre-fills parent_id with current parentId', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('navigateDown', $parent->id)
        ->call('create')
        ->assertSet('form.parent_id', $parent->id);
});

test('create does not pre-fill parent_id at root level', function () {
    $user = User::factory()->withTeam()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('create')
        ->assertSet('form.parent_id', null);
});

test('create resets form before pre-filling parent_id', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
    $item = Item::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Guitar']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('navigateDown', $parent->id)
        ->call('edit', $item->id)
        ->assertSet('form.name', 'Guitar')
        ->call('create')
        ->assertSet('form.name', '')
        ->assertSet('form.parent_id', $parent->id);
});

test('deleting a parent nullifies children parent_id', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->for($user->currentTeam)->location()->create();
    $child = Item::factory()->for($user->currentTeam)->childOf($parent)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('delete', $parent->id);

    expect($child->fresh()->parent_id)->toBeNull();
});
