<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Inventory\GetItem;
use App\Models\Item;
use App\Models\User;

test('it returns full details for an item including its location path', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    $shelf = Item::factory()->bin()->childOf($garage)->create(['name' => 'Shelf 3']);
    $bin = Item::factory()->bin()->childOf($shelf)->create(['name' => 'Blue Bin']);
    $hammer = Item::factory()->item()->childOf($bin)->create([
        'name' => 'Hammer',
        'metadata' => ['brand' => 'DeWalt'],
    ]);

    SunnyServer::actingAs($user)
        ->tool(GetItem::class, ['id' => $hammer->id])
        ->assertOk()
        ->assertSee('Hammer')
        ->assertSee('Type: item')
        ->assertSee('Garage > Shelf 3 > Blue Bin')
        ->assertSee('DeWalt');
});

test('it lists the direct children of an item', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    $shelf = Item::factory()->bin()->childOf($garage)->create(['name' => 'Shelf 3']);
    Item::factory()->item()->childOf($shelf)->create(['name' => 'Grandchild Item']);

    SunnyServer::actingAs($user)
        ->tool(GetItem::class, ['id' => $garage->id])
        ->assertOk()
        ->assertSee('Top level (no parent)')
        ->assertSee('Children (1)')
        ->assertSee('Shelf 3')
        ->assertDontSee('Grandchild Item');
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetItem::class, [])
        ->assertHasErrors();
});

test('it does not expose items from other teams', function () {
    $user = User::factory()->create();
    $otherItem = Item::factory()->create(['name' => 'Secret Widget']);

    SunnyServer::actingAs($user)
        ->tool(GetItem::class, ['id' => $otherItem->id])
        ->assertHasErrors(['Item not found.'])
        ->assertDontSee('Secret Widget');
});
