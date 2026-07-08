<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Inventory\UpdateItem;
use App\Models\Item;
use App\Models\User;

test('it updates the provided fields', function () {
    $user = User::factory()->create();
    $item = Item::factory()->item()->for($user->currentTeam)->create(['name' => 'Hammer']);

    SunnyServer::actingAs($user)
        ->tool(UpdateItem::class, [
            'id' => $item->id,
            'name' => 'Sledgehammer',
            'metadata' => ['brand' => 'DeWalt'],
        ])
        ->assertOk()
        ->assertSee('Updated item')
        ->assertSee('Sledgehammer');

    $item->refresh();

    expect($item->name)->toBe('Sledgehammer')
        ->and($item->metadata)->toBe(['brand' => 'DeWalt']);
});

test('it moves an item to a new parent', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->bin()->for($user->currentTeam)->create(['name' => 'Blue Bin']);
    $item = Item::factory()->item()->for($user->currentTeam)->create(['name' => 'Hammer']);

    SunnyServer::actingAs($user)
        ->tool(UpdateItem::class, ['id' => $item->id, 'parent_id' => $bin->id])
        ->assertOk()
        ->assertSee('Updated item');

    expect($item->refresh()->parent_id)->toBe($bin->id);
});

test('it prevents moving an item inside one of its descendants', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    $shelf = Item::factory()->bin()->childOf($garage)->create(['name' => 'Shelf 3']);
    $bin = Item::factory()->bin()->childOf($shelf)->create(['name' => 'Blue Bin']);

    SunnyServer::actingAs($user)
        ->tool(UpdateItem::class, ['id' => $garage->id, 'parent_id' => $bin->id])
        ->assertHasErrors(['An item cannot be moved inside itself or one of its descendants.']);

    expect($garage->refresh()->parent_id)->toBeNull();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateItem::class, ['name' => 'Sledgehammer'])
        ->assertHasErrors();
});

test('it does not update items belonging to another team', function () {
    $user = User::factory()->create();
    $otherItem = Item::factory()->create(['name' => 'Secret Widget']);

    SunnyServer::actingAs($user)
        ->tool(UpdateItem::class, ['id' => $otherItem->id, 'name' => 'Hijacked'])
        ->assertHasErrors(['Item not found.']);

    expect($otherItem->refresh()->name)->toBe('Secret Widget');
});

test('it rejects a parent belonging to another team', function () {
    $user = User::factory()->create();
    $item = Item::factory()->item()->for($user->currentTeam)->create(['name' => 'Hammer']);
    $otherParent = Item::factory()->bin()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateItem::class, ['id' => $item->id, 'parent_id' => $otherParent->id])
        ->assertHasErrors(['Parent item not found.']);

    expect($item->refresh()->parent_id)->toBeNull();
});
