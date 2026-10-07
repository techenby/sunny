<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Inventory\DuplicateItem;
use App\Models\Item;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it duplicates an item once by default', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Garage']);
    $item = Item::factory()->item()->childOf($garage)->create(['name' => 'Hammer', 'photo_path' => null]);

    SunnyServer::actingAs($user)
        ->tool(DuplicateItem::class, ['id' => $item->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 1)
            ->has('items', 1)
            ->where('items.0.name', 'Hammer')
            ->where('items.0.type', 'item')
            ->where('items.0.parent_id', $garage->id)
            ->where('items.0.id', fn (int $id) => $id !== $item->id));

    expect($user->currentTeam->items()->where('name', 'Hammer')->count())->toBe(2);
});

test('it creates the requested number of copies', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Hammer', 'photo_path' => null]);

    SunnyServer::actingAs($user)
        ->tool(DuplicateItem::class, ['id' => $item->id, 'count' => 3])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 3)
            ->has('items', 3));

    expect($user->currentTeam->items()->where('name', 'Hammer')->count())->toBe(4);
});

test('it rejects a count outside 1 to 25', function (int $count) {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(DuplicateItem::class, ['id' => $item->id, 'count' => $count])
        ->assertHasErrors(['The count must be between 1 and 25.']);
})->with([0, 26]);

test('it cannot duplicate items from other teams', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(DuplicateItem::class, ['id' => $item->id])
        ->assertHasErrors(['Item not found.']);

    expect(Item::query()->count())->toBe(1);
});
