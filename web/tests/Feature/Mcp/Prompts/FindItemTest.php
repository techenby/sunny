<?php

use App\Mcp\Prompts\FindItem;
use App\Mcp\Servers\SunnyServer;
use App\Models\Item;
use App\Models\User;

test('it asks where an item is stored', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->prompt(FindItem::class, ['item' => 'camping lanterns'])
        ->assertOk()
        ->assertSee([
            'Where is "camping lanterns" in our home inventory?',
            'search-items',
            'get-item',
            'trashed true',
        ]);
});

test('it requires an item', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->prompt(FindItem::class)
        ->assertHasErrors(['Tell me what you are looking for.']);
});

test('it completes item names from the current team', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'Camping Lantern']);
    Item::factory()->for($user->currentTeam)->create(['name' => 'Camp Stove']);
    Item::factory()->for($user->currentTeam)->create(['name' => 'Hammer']);
    Item::factory()->create(['name' => 'Camping Chair']);

    SunnyServer::actingAs($user)
        ->completion(FindItem::class, 'item', 'camp')
        ->assertCompletionValues(['Camp Stove', 'Camping Lantern']);
});
