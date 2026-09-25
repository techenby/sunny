<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Inventory\SearchItems;
use App\Models\Item;
use App\Models\User;

test('can search items by name', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'Hammer']);
    Item::factory()->for($user->currentTeam)->create(['name' => 'Screwdriver']);

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['query' => 'Ham'])
        ->assertOk()
        ->assertSee('Found 1 item(s)')
        ->assertSee('Hammer')
        ->assertDontSee('Screwdriver');
});

test('falls back to matching individual words in a multi-word search', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'GoPro Camera Bag']);
    Item::factory()->for($user->currentTeam)->create(['name' => 'Camping Tent']);

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['query' => 'GoPro backpack'])
        ->assertOk()
        ->assertSee('GoPro Camera Bag')
        ->assertDontSee('Camping Tent');
});

test('searches item names case insensitively', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'GoPro Camera Bag']);

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['query' => 'gopro camera bag'])
        ->assertOk()
        ->assertSee('Found 1 item(s)')
        ->assertSee('GoPro Camera Bag');
});

test('prioritizes full phrase matches before individual word matches', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'GoPro Camera Bag']);
    Item::factory()->for($user->currentTeam)->create(['name' => 'GoPro Backpack Strap']);

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['query' => 'GoPro backpack', 'limit' => 1])
        ->assertOk()
        ->assertSee('GoPro Backpack Strap')
        ->assertDontSee('GoPro Camera Bag');
});

test('can filters items by type', function () {
    $user = User::factory()->create();
    Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    Item::factory()->item()->for($user->currentTeam)->create(['name' => 'Hammer']);

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['type' => 'location'])
        ->assertOk()
        ->assertSee('Garage')
        ->assertDontSee('Hammer');
});

test('can lists direct children of a parent item', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    Item::factory()->bin()->childOf($garage)->create(['name' => 'Blue Bin']);
    Item::factory()->item()->for($user->currentTeam)->create(['name' => 'Hammer']);

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['parent_id' => $garage->id])
        ->assertOk()
        ->assertSee('Blue Bin')
        ->assertDontSee('Hammer');
});

test('it includes a hint to use get-item', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'Hammer']);

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, [])
        ->assertOk()
        ->assertSee('get-item');
});

test('rejects a limit above 100', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['limit' => 500])
        ->assertHasErrors();
});

test('rejects an invalid type', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['type' => 'warehouse'])
        ->assertHasErrors();
});

test('does not return items from other teams', function () {
    $user = User::factory()->create();
    Item::factory()->create(['name' => 'Secret GoPro Camera Bag']);

    SunnyServer::actingAs($user)
        ->tool(SearchItems::class, ['query' => 'GoPro backpack'])
        ->assertOk()
        ->assertSee('No items found')
        ->assertDontSee('Secret GoPro Camera Bag');
});
