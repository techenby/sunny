<?php

use App\Enums\TeamRole;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Ramsey\Uuid\Uuid;

test('guests cannot access items', function () {
    $this->getJson(route('api.items.index', 'household'))->assertUnauthorized();
    $this->postJson(route('api.items.store', 'household'))->assertUnauthorized();
    $this->postJson(route('api.items.duplicate', ['household', 1]))->assertUnauthorized();
    $this->postJson(route('api.items.move', ['household', 1]))->assertUnauthorized();
    $this->getJson(route('api.items.show', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.items.update', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.items.destroy', ['household', 1]))->assertUnauthorized();
    $this->postJson(route('api.items.restore', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.items.force-destroy', ['household', 1]))->assertUnauthorized();
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

test('store returns the existing item when a client uuid is retried', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';

    $first = $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Screwdriver', 'type' => 'item', 'client_uuid' => $uuid])
        ->assertCreated()
        ->assertJsonPath('data.client_uuid', $uuid);

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Screwdriver', 'type' => 'item', 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Item::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store returns the existing item when a retry wins the race to insert it', function () {
    Storage::fake();
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $raced = createAfterLookup('items', 'client_uuid', fn () => Item::factory()->for($user->currentTeam)->create(['name' => 'Screwdriver', 'client_uuid' => $uuid]));

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Screwdriver', 'type' => 'item', 'photo' => UploadedFile::fake()->image('screwdriver.jpg'), 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $raced->model->id);

    expect(Item::where('client_uuid', $uuid)->count())->toBe(1)
        ->and(ItemPhoto::count())->toBe(0)
        ->and(Storage::allFiles())->toBeEmpty();
});

test('store rejects a client uuid that is not a uuid', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Screwdriver', 'type' => 'item', 'client_uuid' => 'nope'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_uuid']);
});

test('duplicate does not copy the client uuid', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['client_uuid' => '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d']);

    $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$user->currentTeam, $item]), ['count' => 2])
        ->assertCreated()
        ->assertJsonPath('data.0.client_uuid', null)
        ->assertJsonPath('data.1.client_uuid', null);
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

test('duplicate returns the same copies when a client uuid is retried', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Wrench']);
    $payload = ['count' => 2, 'client_uuid' => '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d'];

    $first = $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$user->currentTeam, $item]), $payload)
        ->assertCreated()
        ->assertJsonCount(2, 'data');

    $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$user->currentTeam, $item]), $payload)
        ->assertOk()
        ->assertJsonPath('data.*.id', $first->json('data.*.id'));

    expect($user->currentTeam->items()->where('name', 'Wrench')->count())->toBe(3);
});

test('duplicate returns the existing copy when a retry wins the race to insert it', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Wrench']);
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $raced = createAfterLookup('items', 'client_uuid', fn () => Item::factory()->for($user->currentTeam)->create([
        'name' => 'Wrench',
        'client_uuid' => Uuid::uuid5($uuid, '1')->toString(),
    ]));

    $this->actingAs($user)
        ->postJson(route('api.items.duplicate', [$user->currentTeam, $item]), ['client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.0.id', $raced->model->id);

    expect($user->currentTeam->items()->where('name', 'Wrench')->count())->toBe(2);
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

test('show returns the primary photo and every photo in order', function () {
    Storage::fake();

    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    ItemPhoto::factory()->for($item)->create(['path' => "teams/{$user->current_team_id}/items/wrench-back.png", 'position' => 1]);
    $front = ItemPhoto::factory()->for($item)->create(['path' => "teams/{$user->current_team_id}/items/wrench.png", 'thumb_path' => "teams/{$user->current_team_id}/items/thumbs/wrench.jpg", 'position' => 0]);

    $response = $this->actingAs($user)
        ->getJson(route('api.items.show', [$user->currentTeam, $item]))
        ->assertOk()
        ->assertJsonMissingPath('data.photo_path')
        ->assertJsonMissingPath('data.thumb_path')
        ->assertJsonMissingPath('data.primary_photo')
        ->assertJsonCount(2, 'data.photos')
        ->assertJsonPath('data.photos.0.id', $front->id)
        ->assertJsonPath('data.photos.0.item_id', $item->id)
        ->assertJsonPath('data.photos.0.position', 0)
        ->assertJsonPath('data.photos.1.position', 1)
        ->assertJsonPath('data.photos.1.thumb_url', null)
        ->assertJsonMissingPath('data.photos.0.path');

    expect($response->json('data.photo_url'))->toContain('wrench.png')
        ->and($response->json('data.thumb_url'))->toContain('thumbs/wrench.jpg')
        ->and($response->json('data.photos.1.url'))->toContain('wrench-back.png');
});

test('show returns null photo_url when item has no photo', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.items.show', [$user->currentTeam, $item]))
        ->assertOk()
        ->assertJsonPath('data.photo_url', null)
        ->assertJsonPath('data.photos', []);
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

test('a move that sends only parent_id keeps a rename made on the web and the existing details', function (?string $destination) {
    $user = User::factory()->create();
    $garage = Item::factory()->for($user->currentTeam)->create(['name' => 'Garage', 'type' => 'location']);
    $shelf = Item::factory()->for($user->currentTeam)->create(['name' => 'Shelf', 'type' => 'location']);
    $tent = Item::factory()->for($user->currentTeam)->create([
        'name' => 'Tent',
        'parent_id' => $garage->id,
        'metadata' => ['brand' => 'REI'],
    ]);

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $tent])
        ->call('edit')
        ->set('form.name', 'Big tent')
        ->call('save')
        ->assertHasNoErrors();

    $parentId = $destination === null ? null : $shelf->id;

    $this->actingAs($user)
        ->patchJson(route('api.items.update', [$user->currentTeam, $tent]), ['parent_id' => $parentId])
        ->assertOk()
        ->assertJsonPath('data.name', 'Big tent')
        ->assertJsonPath('data.parent_id', $parentId);

    expect($tent->fresh())
        ->name->toBe('Big tent')
        ->parent_id->toBe($parentId)
        ->metadata->toBe(['brand' => 'REI']);
})->with([
    'into another place' => ['shelf'],
    'to the top level' => [null],
]);

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

test('restore brings back a deleted item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Hammer']);
    $item->delete();

    $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $item]))
        ->assertOk()
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonPath('data.name', 'Hammer')
        ->assertJsonPath('data.deleted_at', null);

    expect($item->fresh()->trashed())->toBeFalse();
});

test('restore puts an item back inside its place', function () {
    $user = User::factory()->create();
    $place = Item::factory()->location()->for($user->currentTeam)->create();
    $item = Item::factory()->childOf($place)->create();
    $item->delete();

    $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $item]))
        ->assertOk()
        ->assertJsonPath('data.parent_id', $place->id);
});

test('restore moves an item to the top level when its place is still deleted', function () {
    $user = User::factory()->create();
    $place = Item::factory()->location()->for($user->currentTeam)->create();
    $item = Item::factory()->childOf($place)->create();
    $item->delete();
    $place->purge();

    $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $item]))
        ->assertOk()
        ->assertJsonPath('data.parent_id', null);

    expect($item->fresh())->trashed()->toBeFalse()->parent_id->toBeNull()
        ->and($place->fresh()->trashed())->toBeTrue();
});

test('restoring a container leaves what was inside it at the top level', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->bin()->for($user->currentTeam)->create();
    $child = Item::factory()->childOf($bin)->create();
    $bin->purge();

    $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $bin]))
        ->assertOk();

    expect($child->fresh()->parent_id)->toBeNull();
});

test('restore leaves an item that is not deleted as it is', function () {
    $user = User::factory()->create();
    $place = Item::factory()->location()->for($user->currentTeam)->create();
    $item = Item::factory()->childOf($place)->create(['updated_at' => now()->subDay()]);
    $before = $this->actingAs($user)->getJson(route('api.items.show', [$user->currentTeam, $item]))->json('data');

    $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $item]))
        ->assertOk()
        ->assertJsonPath('data', $before)
        ->assertJsonPath('data.parent_id', $place->id);

    expect($item->fresh()->updated_at->toIso8601String())->toBe($item->updated_at->toIso8601String());
});

test('a retried restore returns the item again without changing it', function () {
    $user = User::factory()->create();
    $place = Item::factory()->location()->for($user->currentTeam)->create();
    $item = Item::factory()->childOf($place)->create();
    $item->delete();
    $place->purge();

    $first = $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $item]))
        ->assertOk()
        ->json('data');
    $place->restore();

    $this->travel(1)->minute();

    $retry = $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $item]))
        ->assertOk()
        ->json('data');

    expect($retry)->toEqual($first)
        ->and($item->fresh()->parent_id)->toBeNull();
});

test('restore returns 404 for an item that was permanently deleted', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $item->forceDelete();

    $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $item->id]))
        ->assertNotFound();
});

test('restore returns 403 for a team the user does not belong to item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();
    $item->delete();

    $this->actingAs($user)
        ->postJson(route('api.items.restore', [$item->team, $item]))
        ->assertForbidden();

    expect($item->fresh()->trashed())->toBeTrue();
});

test('restore returns 404 when the deleted item belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $item = Item::factory()->for($team)->create();
    $item->delete();

    $this->actingAs($user)
        ->postJson(route('api.items.restore', [$user->currentTeam, $item]))
        ->assertNotFound();

    expect($item->fresh()->trashed())->toBeTrue();
});

test('force destroy permanently deletes a deleted item and its photo files', function () {
    Storage::fake();
    Storage::put('teams/1/items/front.jpg', 'front');
    Storage::put('teams/1/items/thumbs/front.jpg', 'thumb');
    Storage::put('teams/1/items/back.jpg', 'back');
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $front = ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/front.jpg', 'thumb_path' => 'teams/1/items/thumbs/front.jpg', 'position' => 0]);
    $back = ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/back.jpg', 'position' => 1]);
    $item->delete();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$user->currentTeam, $item]))
        ->assertNoContent();

    expect(Item::withTrashed()->find($item->id))->toBeNull()
        ->and($front->fresh())->toBeNull()
        ->and($back->fresh())->toBeNull();
    Storage::assertMissing(['teams/1/items/front.jpg', 'teams/1/items/thumbs/front.jpg', 'teams/1/items/back.jpg']);
});

test('force destroy moves deleted things that were inside the item to the top level', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->bin()->for($user->currentTeam)->create();
    $child = Item::factory()->childOf($bin)->create();
    $child->delete();
    $bin->purge();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$user->currentTeam, $bin]))
        ->assertNoContent();

    expect($child->fresh())->trashed()->toBeTrue()->parent_id->toBeNull();
});

test('force destroy returns 404 for an item that is not deleted', function () {
    Storage::fake();
    Storage::put('teams/1/items/front.jpg', 'front');
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/front.jpg']);

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$user->currentTeam, $item]))
        ->assertNotFound();

    expect($item->fresh())->not->toBeNull();
    Storage::assertExists('teams/1/items/front.jpg');
});

test('force destroy returns 403 for a team the user does not belong to item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();
    $item->delete();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$item->team, $item]))
        ->assertForbidden();

    expect(Item::withTrashed()->find($item->id))->not->toBeNull();
});

test('a retried force destroy succeeds once the item is gone', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $item->delete();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$user->currentTeam, $item]))
        ->assertNoContent();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$user->currentTeam, $item]))
        ->assertNoContent();
});

test('force destroy succeeds for an item that never existed', function () {
    $user = User::factory()->create();
    $kept = Item::factory()->for($user->currentTeam)->create();
    $kept->delete();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$user->currentTeam, 999999]))
        ->assertNoContent();

    expect(Item::withTrashed()->count())->toBe(1);
});

test('force destroy returns 404 for a deleted item that belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $item = Item::factory()->for($team)->create();
    $item->delete();
    $stranger = Item::factory()->create();
    $stranger->delete();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$user->currentTeam, $item]))
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$user->currentTeam, $stranger]))
        ->assertNotFound();

    expect(Item::withTrashed()->find($item->id))->not->toBeNull()
        ->and(Item::withTrashed()->find($stranger->id))->not->toBeNull();
});

test('force destroy does not answer for a missing item on a team the user does not belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', [$team, 999999]))
        ->assertNotFound();

    $this->actingAs($user)
        ->deleteJson(route('api.items.force-destroy', ['no-such-team', 999999]))
        ->assertNotFound();
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

test('update rejects putting an item inside itself or inside something it holds', function (string $target) {
    $user = User::factory()->create();
    $shelf = Item::factory()->for($user->currentTeam)->create(['name' => 'Shelf']);
    $bin = Item::factory()->for($user->currentTeam)->childOf($shelf)->create(['name' => 'Bin']);
    $box = Item::factory()->for($user->currentTeam)->childOf($bin)->create(['name' => 'Box']);

    $this->actingAs($user)
        ->patchJson(route('api.items.update', [$user->currentTeam, $shelf]), ['parent_id' => ['itself' => $shelf, 'something it holds' => $box][$target]->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('parent_id');

    expect($shelf->fresh()->parent_id)->toBeNull();
})->with(['itself', 'something it holds']);

test('item API accepts JSON metadata alongside a photo from older apps and removes it explicitly', function () {
    Storage::fake();
    $user = User::factory()->create();
    $response = $this->actingAs($user)->post(route('api.items.store', $user->currentTeam), [
        'name' => 'Measuring tape', 'type' => 'item', 'metadata' => json_encode(['size[inches]' => '12']),
        'photo' => UploadedFile::fake()->image('tape.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.metadata', ['size[inches]' => '12'])->assertJsonCount(1, 'data.photos');
    $item = Item::findOrFail($response->json('data.id'));
    $photo = $item->photos()->sole();
    Storage::assertExists($photo->path);
    Storage::assertExists($photo->thumb_path);
    expect($response->json('data.thumb_url'))->toContain('/thumbs/');
    $this->post(route('api.items.update', [$user->currentTeam, $item]), [
        '_method' => 'PATCH', 'metadata' => json_encode(['size[inches]' => '24']),
        'photo' => UploadedFile::fake()->image('tape.png'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('data.metadata', ['size[inches]' => '24'])->assertJsonCount(1, 'data.photos');
    Storage::assertMissing($photo->path);
    Storage::assertMissing($photo->thumb_path);
    $photo = $item->photos()->sole();
    Storage::assertExists($photo->thumb_path);
    $this->patchJson(route('api.items.update', [$user->currentTeam, $item]), ['remove_photo' => true, 'metadata' => null])
        ->assertOk()->assertJsonPath('data.photo_url', null)->assertJsonPath('data.thumb_url', null)->assertJsonPath('data.metadata', null)->assertJsonPath('data.photos', []);
    Storage::assertMissing($photo->path);
    Storage::assertMissing($photo->thumb_path);
});

test('a photo from an older app replaces only the primary photo', function () {
    Storage::fake();
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $front = ItemPhoto::factory()->for($item)->create(['position' => 0]);
    $back = ItemPhoto::factory()->for($item)->create(['position' => 1]);

    $response = $this->actingAs($user)->post(route('api.items.update', [$user->currentTeam, $item]), [
        '_method' => 'PATCH', 'photo' => UploadedFile::fake()->image('new-front.jpg'),
    ], ['Accept' => 'application/json'])->assertOk()->assertJsonCount(2, 'data.photos');

    $photos = $item->photos()->get();

    expect($front->fresh())->toBeNull()
        ->and($photos->pluck('position')->all())->toBe([0, 1])
        ->and($photos->last()->id)->toBe($back->id)
        ->and($response->json('data.photo_url'))->toContain($photos->first()->path);
});

test('removing the photo from an older app removes only the primary photo', function () {
    Storage::fake();
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $front = ItemPhoto::factory()->for($item)->create(['position' => 0]);
    $back = ItemPhoto::factory()->for($item)->create(['position' => 1]);

    $this->actingAs($user)
        ->patchJson(route('api.items.update', [$user->currentTeam, $item]), ['remove_photo' => true])
        ->assertOk()
        ->assertJsonCount(1, 'data.photos')
        ->assertJsonPath('data.photos.0.id', $back->id)
        ->assertJsonPath('data.photos.0.position', 0);

    expect($front->fresh())->toBeNull();
});

test('index includes each item\'s photos', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    ItemPhoto::factory()->for($item)->count(2)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

    $this->actingAs($user)
        ->getJson(route('api.items.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(2, 'data.0.photos');
});

test('update puts the photos in the order given', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    [$front, $back, $scratch, $label] = ItemPhoto::factory()->for($item)->count(4)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();
    $elsewhere = ItemPhoto::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.update', [$user->currentTeam, $item]), ['photo_order' => [$scratch->id, 999, $elsewhere->id, $front->id]])
        ->assertOk()
        ->assertJsonPath('data.photos.0.id', $scratch->id)
        ->assertJsonPath('data.photos.1.id', $front->id)
        ->assertJsonPath('data.photos.2.id', $back->id)
        ->assertJsonPath('data.photos.3.id', $label->id);

    expect($item->photos()->pluck('position')->all())->toBe([0, 1, 2, 3])
        ->and($elsewhere->fresh()->position)->toBe(0);
});

test('update validates the photo order', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.update', [$user->currentTeam, $item]), ['photo_order' => ['front']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('photo_order.0');
});

test('move takes the item and everything inside it to another team', function () {
    $user = User::factory()->create();
    $cabin = Team::factory()->create();
    $cabin->members()->attach($user, ['role' => TeamRole::Member->value]);
    $garage = Item::factory()->location()->for($user->currentTeam)->create();
    $tote = Item::factory()->bin()->childOf($garage)->create();
    $bag = Item::factory()->bin()->childOf($tote)->create();
    $hammer = Item::factory()->item()->childOf($bag)->create();

    $this->actingAs($user)
        ->postJson(route('api.items.move', [$user->currentTeam, $tote]), ['team_id' => $cabin->id])
        ->assertOk()
        ->assertJsonPath('data.id', $tote->id)
        ->assertJsonPath('data.team_id', $cabin->id)
        ->assertJsonPath('data.parent_id', null)
        ->assertJsonPath('moved_item_ids', fn (array $ids) => collect($ids)->sort()->values()->all() === [$tote->id, $bag->id, $hammer->id]);

    expect($bag->fresh())->team_id->toBe($cabin->id)->parent_id->toBe($tote->id)
        ->and($hammer->fresh())->team_id->toBe($cabin->id)->parent_id->toBe($bag->id)
        ->and($garage->fresh()->team_id)->toBe($user->current_team_id);
});

test('move returns 403 for a team the user does not belong to item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();
    $originalTeamId = $item->team_id;

    $this->actingAs($user)
        ->postJson(route('api.items.move', [$item->team, $item]), ['team_id' => $user->current_team_id])
        ->assertForbidden();

    expect($item->fresh()->team_id)->toBe($originalTeamId);
});

test('move rejects a team the user does not belong to', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $otherTeam = Team::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.move', [$user->currentTeam, $item]), ['team_id' => $otherTeam->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('team_id');

    expect($item->fresh()->team_id)->toBe($user->current_team_id);
});

test('move validates the team is given and is not the item\'s own team', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->postJson(route('api.items.move', [$user->currentTeam, $item]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('team_id');

    $this->actingAs($user)
        ->postJson(route('api.items.move', [$user->currentTeam, $item]), ['team_id' => $user->current_team_id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('team_id');
});

test('store and update accept details that are text', function () {
    $user = User::factory()->create();

    $item = $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Drill', 'type' => 'item', 'metadata' => ['brand' => 'DeWalt', 'volts' => '20']])
        ->assertCreated()
        ->assertJsonPath('data.metadata', ['brand' => 'DeWalt', 'volts' => '20']);

    $this->patchJson(route('api.items.update', [$user->currentTeam, $item->json('data.id')]), ['metadata' => ['brand' => 'Makita']])
        ->assertOk()
        ->assertJsonPath('data.metadata', ['brand' => 'Makita']);
});

test('store and update refuse details that are not text', function (array $metadata) {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['metadata' => ['brand' => 'DeWalt']]);

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Drill', 'type' => 'item', 'metadata' => $metadata])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metadata' => 'Each detail must be text.']);

    $this->patchJson(route('api.items.update', [$user->currentTeam, $item]), ['metadata' => $metadata])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metadata' => 'Each detail must be text.']);

    expect(Item::query()->count())->toBe(1)
        ->and($item->refresh()->metadata)->toBe(['brand' => 'DeWalt']);
})->with([
    'nested' => [['brand' => ['name' => 'DeWalt']]],
    'number' => [['volts' => 20]],
]);

test('store refuses a list of details, too many details, blank labels and details that are too long', function (array $metadata, string $message) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Drill', 'type' => 'item', 'metadata' => $metadata])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['metadata' => $message]);
})->with([
    'list' => [['DeWalt', '20'], 'The details must be labels with text values.'],
    'too many' => [array_fill_keys(array_map(fn (int $number) => "label {$number}", range(1, 51)), 'value'), 'An item can have at most 50 details.'],
    'empty label' => [['' => 'value'], 'Each detail needs a label.'],
    'blank label' => [['   ' => 'value'], 'Each detail needs a label.'],
    'label too long' => [[str_repeat('a', 256) => 'value'], 'Each detail label and value must be at most 255 characters.'],
    'value too long' => [['label' => str_repeat('a', 256)], 'Each detail label and value must be at most 255 characters.'],
]);
