<?php

use App\Mcp\Tools\Inventory\DeleteItem;
use App\Models\Item;
use App\Models\User;
use Tests\Feature\Mcp\SunnyTestServer;

test('it soft deletes an item and detaches its children', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    $bin = Item::factory()->bin()->childOf($garage)->create(['name' => 'Blue Bin']);

    SunnyTestServer::actingAs($user)
        ->tool(DeleteItem::class, ['id' => $garage->id])
        ->assertOk()
        ->assertSee('Garage')
        ->assertSee('restore-item');

    expect($garage->fresh())->toBeTrashed()
        ->and($bin->fresh()->parent_id)->toBeNull()
        ->and($bin->fresh()->trashed())->toBeFalse();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteItem::class)
        ->assertHasErrors();
});

test('it cannot delete items from other teams', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(DeleteItem::class, ['id' => $item->id])
        ->assertHasErrors(['Item not found.']);

    expect($item->fresh()->trashed())->toBeFalse();
});
