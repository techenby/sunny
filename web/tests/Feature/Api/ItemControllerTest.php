<?php

use App\Enums\TeamRole;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
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
