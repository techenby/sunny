<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Inventory\CreateItem;
use App\Models\Item;
use App\Models\User;

test('it creates an item for the current team', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateItem::class, [
            'name' => 'Hammer',
            'type' => 'item',
            'metadata' => ['brand' => 'DeWalt'],
        ])
        ->assertOk()
        ->assertSee('Created item')
        ->assertSee('Hammer');

    $item = Item::query()->sole();

    expect($item->team_id)->toBe($user->currentTeam->id)
        ->and($item->name)->toBe('Hammer')
        ->and($item->metadata)->toBe(['brand' => 'DeWalt']);
});

test('it creates an item inside a parent', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->bin()->for($user->currentTeam)->create(['name' => 'Blue Bin']);

    SunnyServer::actingAs($user)
        ->tool(CreateItem::class, [
            'name' => 'Hammer',
            'type' => 'item',
            'parent_id' => $bin->id,
        ])
        ->assertOk()
        ->assertSee('Created item');

    expect(Item::query()->where('name', 'Hammer')->sole()->parent_id)->toBe($bin->id);
});

test('it requires a name and a valid type', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateItem::class, ['type' => 'warehouse'])
        ->assertHasErrors();

    expect(Item::query()->count())->toBe(0);
});

test('it rejects a parent belonging to another team', function () {
    $user = User::factory()->create();
    $otherParent = Item::factory()->bin()->create();

    SunnyServer::actingAs($user)
        ->tool(CreateItem::class, [
            'name' => 'Hammer',
            'type' => 'item',
            'parent_id' => $otherParent->id,
        ])
        ->assertHasErrors(['Parent item not found.']);

    expect(Item::query()->where('name', 'Hammer')->count())->toBe(0);
});
