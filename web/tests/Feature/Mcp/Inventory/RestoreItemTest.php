<?php

use App\Mcp\Tools\Inventory\RestoreItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('it restores a deleted item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->item()->for($user->currentTeam)->create(['name' => 'Hammer']);
    $item->delete();

    SunnyTestServer::actingAs($user)
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

test('it moves the item to the top level when its parent is still deleted', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->bin()->for($user->currentTeam)->create();
    $item = Item::factory()->childOf($bin)->create();
    $item->delete();
    $bin->purge();

    SunnyTestServer::actingAs($user)
        ->tool(RestoreItem::class, ['id' => $item->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $item->id)
            ->where('parent_id', null)
            ->etc());
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(RestoreItem::class)
        ->assertHasErrors();
});

test('it returns an item that is not deleted unchanged, so a retry is safe', function () {
    $user = User::factory()->create();
    $item = Item::factory()->item()->for($user->currentTeam)->create(['name' => 'Hammer']);

    SunnyTestServer::actingAs($user)
        ->tool(RestoreItem::class, ['id' => $item->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $item->id)
            ->where('name', 'Hammer')
            ->etc());

    expect($item->fresh()->trashed())->toBeFalse();
});

test('it cannot restore items from other teams', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();
    $item->delete();

    SunnyTestServer::actingAs($user)
        ->tool(RestoreItem::class, ['id' => $item->id])
        ->assertHasErrors(['Item not found.']);

    expect($item->fresh())->toBeTrashed();
});
