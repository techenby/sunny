<?php

use App\Enums\TeamRole;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

test('guests cannot access items', function () {
    $this->getJson(route('api.items.index', 'household'))->assertUnauthorized();
    $this->postJson(route('api.items.store', 'household'))->assertUnauthorized();
    $this->postJson(route('api.items.duplicate', ['household', 1]))->assertUnauthorized();
    $this->getJson(route('api.items.show', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.items.update', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.items.destroy', ['household', 1]))->assertUnauthorized();
});

test('index returns items for the current team', function () {
    $user = User::factory()->create();
    Item::factory()->for($user->currentTeam)->count(3)->create();
    Item::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.items.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'type']]]);
});

test('store creates an item and returns it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), [
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
        ->postJson(route('api.items.store', $user->currentTeam), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'type']);
});

test('store validates type is a valid enum', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), [
            'name' => 'Test',
            'type' => 'invalid',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['type']);
});

test('duplicate creates copies and returns them', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Wrench']);

    $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$user->currentTeam, $item]), ['count' => 3])
        ->assertCreated()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('data.0.name', 'Wrench');

    expect($user->currentTeam->items()->where('name', 'Wrench')->count())->toBe(4);
});

test('duplicate defaults to a single copy', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$user->currentTeam, $item]))
        ->assertCreated()
        ->assertJsonCount(1, 'data');

    expect(Item::count())->toBe(2);
});

test('duplicate validates count is between 1 and 25', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$user->currentTeam, $item]), ['count' => 0])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['count']);

    $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$user->currentTeam, $item]), ['count' => 26])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['count']);

    expect(Item::count())->toBe(1);
});

test('duplicate returns 403 for a team the user does not belong to item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$item->team, $item]), ['count' => 2])
        ->assertForbidden();

    expect(Item::count())->toBe(1);
});

test('show returns an item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.items.show', [$user->currentTeam, $item]))
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
        ->getJson(route('api.items.show', [$user->currentTeam, $item]))
        ->assertOk()
        ->assertJsonMissingPath('data.photo_path');

    expect($response->json('data.photo_url'))->toBeString()->toContain('wrench.png');
});

test('show returns null photo_url when item has no photo', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['photo_path' => null]);

    $this->actingAs($user)
        ->getJson(route('api.items.show', [$user->currentTeam, $item]))
        ->assertOk()
        ->assertJsonPath('data.photo_url', null);
});

test('show returns 403 for a team the user does not belong to item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.items.show', [$item->team, $item]))
        ->assertForbidden();
});

test('update modifies an item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.update', [$user->currentTeam, $item]), [
            'name' => 'Updated Name',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Updated Name');

    $this->assertDatabaseHas('items', [
        'id' => $item->id,
        'name' => 'Updated Name',
    ]);
});

test('update returns 403 for a team the user does not belong to item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.update', [$item->team, $item]), ['name' => 'Nope'])
        ->assertForbidden();
});

test('destroy deletes an item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.items.destroy', [$user->currentTeam, $item]))
        ->assertNoContent();

    $this->assertSoftDeleted('items', ['id' => $item->id]);
});

test('destroy returns 403 for a team the user does not belong to item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.items.destroy', [$item->team, $item]))
        ->assertForbidden();
});

test('index returns items for another team the user belongs to without switching teams', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    Item::factory()->for($team)->count(2)->create();
    Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.items.index', $team))
        ->assertOk()
        ->assertJsonCount(2, 'data');

    expect($user->fresh()->current_team_id)->not->toBe($team->id);
});

test('store creates the item in the team from the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->postJson(route('api.items.store', $team), ['name' => 'Screwdriver', 'type' => 'item'])
        ->assertCreated();

    expect($team->items()->count())->toBe(1)
        ->and($user->currentTeam->items()->count())->toBe(0);
});

test('show returns 404 when the item belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $item = Item::factory()->for($team)->create();

    $this->actingAs($user)
        ->getJson(route('api.items.show', [$user->currentTeam, $item]))
        ->assertNotFound();
});

test('store rejects a parent from another team', function () {
    $user = User::factory()->create();
    $parent = Item::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Screwdriver', 'type' => 'item', 'parent_id' => $parent->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');
});
