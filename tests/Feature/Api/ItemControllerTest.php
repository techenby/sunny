<?php

use App\Models\Item;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests cannot access items', function () {
    $this->getJson(route('api.items.index'))->assertUnauthorized();
    $this->postJson(route('api.items.store'))->assertUnauthorized();
    $this->getJson(route('api.items.show', 1))->assertUnauthorized();
    $this->patchJson(route('api.items.update', 1))->assertUnauthorized();
    $this->deleteJson(route('api.items.destroy', 1))->assertUnauthorized();
});

test('index returns items for the current team', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->count(3)->create();
    Item::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.items.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'type']]]);
});

test('store creates an item and returns it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store'), [
            'name' => 'Screwdriver',
            'type' => 'item',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Screwdriver')
        ->assertJsonPath('data.type', 'item');

    $this->assertDatabaseHas('items', [
        'team_id' => $user->current_team_id,
        'name' => 'Screwdriver',
        'type' => 'item',
    ]);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store'), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'type']);
});

test('store validates type is a valid enum', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store'), [
            'name' => 'Test',
            'type' => 'invalid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('show returns an item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.items.show', $item))
        ->assertOk()
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonPath('data.name', $item->name);
});

test('show returns photo_url when item has a photo', function () {
    Storage::fake();

    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create([
        'photo_path' => "teams/{$user->current_team_id}/items/wrench.png",
    ]);

    $response = $this->actingAs($user)
        ->getJson(route('api.items.show', $item))
        ->assertOk()
        ->assertJsonMissingPath('data.photo_path');

    expect($response->json('data.photo_url'))->toBeString()->toContain('wrench.png');
});

test('show returns null photo_url when item has no photo', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['photo_path' => null]);

    $this->actingAs($user)
        ->getJson(route('api.items.show', $item))
        ->assertOk()
        ->assertJsonPath('data.photo_url', null);
});

test('show returns 403 for another team item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.items.show', $item))
        ->assertForbidden();
});

test('update modifies an item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.update', $item), [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');

    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'name' => 'Updated Name',
    ]);
});

test('update returns 403 for another team item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.update', $item), ['name' => 'Nope'])
        ->assertForbidden();
});

test('destroy deletes an item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.items.destroy', $item))
        ->assertNoContent();

    $this->assertSoftDeleted('items', ['id' => $item->id]);
});

test('destroy returns 403 for another team item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.items.destroy', $item))
        ->assertForbidden();
});
