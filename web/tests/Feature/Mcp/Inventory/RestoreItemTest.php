<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Inventory\RestoreItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it restores a deleted item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->item()->for($user->currentTeam)->create(['name' => 'Hammer']);
    $item->delete();

    SunnyServer::actingAs($user)
        ->tool(RestoreItem::class, ['id' => $item->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $item->id)
            ->where('name', 'Hammer')
            ->where('type', 'item')
            ->where('parent_id', null)
            ->has('updated_at'));

    expect($item->fresh()->trashed())->toBeFalse();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(RestoreItem::class)
        ->assertHasErrors();
});

test('it does not restore an item that is not deleted', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(RestoreItem::class, ['id' => $item->id])
        ->assertHasErrors(['Deleted item not found.']);
});

test('it cannot restore items from other teams', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();
    $item->delete();

    SunnyServer::actingAs($user)
        ->tool(RestoreItem::class, ['id' => $item->id])
        ->assertHasErrors(['Deleted item not found.']);

    expect($item->fresh())->toBeTrashed();
});
