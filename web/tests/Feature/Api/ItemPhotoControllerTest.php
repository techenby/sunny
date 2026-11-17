<?php

use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

test('guests cannot manage item photos', function () {
    $this->postJson(route('api.items.photos.store', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.items.photos.update', ['household', 1, 1]))->assertUnauthorized();
    $this->deleteJson(route('api.items.photos.destroy', ['household', 1, 1]))->assertUnauthorized();
});

test('store adds a photo to the end and makes a thumbnail', function () {
    Storage::fake();
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->withPhoto('teams/1/items/front.jpg')->create(['name' => 'Tent']);

    $response = $this->actingAs($user)
        ->post(route('api.items.photos.store', [$user->currentTeam, $item]), [
            'photo' => UploadedFile::fake()->image('back.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.item_id', $item->id)
        ->assertJsonPath('data.position', 1);

    $photo = ItemPhoto::findOrFail($response->json('data.id'));

    expect($photo->path)->toStartWith("teams/{$item->team_id}/items/tent-{$item->id}-");
    Storage::assertExists($photo->path);
    Storage::assertExists($photo->thumb_path);
    expect($response->json('data.thumb_url'))->toContain('/thumbs/');
});

test('store can put the new photo first', function () {
    Storage::fake();
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $front = ItemPhoto::factory()->for($item)->create(['position' => 0]);

    $response = $this->actingAs($user)
        ->post(route('api.items.photos.store', [$user->currentTeam, $item]), [
            'photo' => UploadedFile::fake()->image('new.jpg'),
            'position' => 0,
        ], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.position', 0);

    expect($item->photos()->pluck('id')->all())->toBe([$response->json('data.id'), $front->id]);
});

test('store returns the existing photo when a client uuid is retried', function () {
    Storage::fake();
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $uuid = (string) Str::uuid();

    $first = $this->actingAs($user)
        ->post(route('api.items.photos.store', [$user->currentTeam, $item]), [
            'photo' => UploadedFile::fake()->image('one.jpg'), 'client_uuid' => $uuid,
        ], ['Accept' => 'application/json'])
        ->assertCreated();

    $this->post(route('api.items.photos.store', [$user->currentTeam, $item]), [
        'photo' => UploadedFile::fake()->image('one.jpg'), 'client_uuid' => $uuid,
    ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'))
        ->assertJsonPath('data.client_uuid', $uuid);

    expect($item->photos()->count())->toBe(1);
});

test('store returns the existing photo and drops the upload when a retry wins the race to insert it', function () {
    Storage::fake();
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $other = ItemPhoto::factory()->for($item)->create(['position' => 0]);
    $uuid = (string) Str::uuid();
    $raced = createAfterLookup('item_photos', 'client_uuid', fn () => ItemPhoto::factory()->for($item)->create(['position' => 1, 'client_uuid' => $uuid]), lookup: 2);

    $this->actingAs($user)
        ->post(route('api.items.photos.store', [$user->currentTeam, $item]), [
            'photo' => UploadedFile::fake()->image('one.jpg'), 'position' => 0, 'client_uuid' => $uuid,
        ], ['Accept' => 'application/json'])
        ->assertOk()
        ->assertJsonPath('data.id', $raced->model->id)
        ->assertJsonPath('data.position', 1);

    expect($item->photos()->pluck('id')->all())->toBe([$other->id, $raced->model->id])
        ->and(Storage::allFiles())->toBeEmpty();
});

test('store allows up to ten photos', function () {
    Storage::fake();
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $uuid = (string) Str::uuid();
    ItemPhoto::factory()->for($item)->count(9)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();
    ItemPhoto::factory()->for($item)->create(['position' => 9, 'client_uuid' => $uuid]);

    $this->actingAs($user)
        ->post(route('api.items.photos.store', [$user->currentTeam, $item]), [
            'photo' => UploadedFile::fake()->image('eleven.jpg'),
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photo' => 'An item can have up to 10 photos.']);

    $this->post(route('api.items.photos.store', [$user->currentTeam, $item]), [
        'photo' => UploadedFile::fake()->image('tenth-again.jpg'), 'client_uuid' => $uuid,
    ], ['Accept' => 'application/json'])->assertOk();

    expect($item->photos()->count())->toBe(10);
});

test('store validates the photo', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->post(route('api.items.photos.store', [$user->currentTeam, $item]), [
            'photo' => UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf'),
            'position' => -1,
            'client_uuid' => 'nope',
        ], ['Accept' => 'application/json'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['photo', 'position', 'client_uuid']);
});

test('update moves a photo and shifts the others', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    [$front, $back, $scratch] = ItemPhoto::factory()->for($item)->count(3)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.photos.update', [$user->currentTeam, $item, $scratch]), ['position' => 0])
        ->assertOk()
        ->assertJsonPath('data.id', $scratch->id)
        ->assertJsonPath('data.position', 0);

    expect($item->photos()->pluck('id')->all())->toBe([$scratch->id, $front->id, $back->id])
        ->and($item->photos()->pluck('position')->all())->toBe([0, 1, 2]);
});

test('update puts a photo last when the position is past the end', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    [$front, $back] = ItemPhoto::factory()->for($item)->count(2)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.photos.update', [$user->currentTeam, $item, $front]), ['position' => 99])
        ->assertOk()
        ->assertJsonPath('data.position', 1);

    expect($item->photos()->pluck('id')->all())->toBe([$back->id, $front->id]);
});

test('update requires a position', function () {
    $user = User::factory()->create();
    $photo = ItemPhoto::factory()->for(Item::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->patchJson(route('api.items.photos.update', [$user->currentTeam, $photo->item, $photo]), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('position');
});

test('destroy removes the photo, its files and closes the gap', function () {
    Storage::fake();
    Storage::put('teams/1/items/front.jpg', 'front');
    Storage::put('teams/1/items/thumbs/front.jpg', 'thumb');
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $front = ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/front.jpg', 'thumb_path' => 'teams/1/items/thumbs/front.jpg', 'position' => 0]);
    $back = ItemPhoto::factory()->for($item)->create(['position' => 1]);

    $this->actingAs($user)
        ->deleteJson(route('api.items.photos.destroy', [$user->currentTeam, $item, $front]))
        ->assertNoContent();

    expect($front->fresh())->toBeNull()
        ->and($back->fresh()->position)->toBe(0);
    Storage::assertMissing(['teams/1/items/front.jpg', 'teams/1/items/thumbs/front.jpg']);
});

test('changing photos marks the item as updated for sync', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['updated_at' => now()->subDay()]);
    $photo = ItemPhoto::factory()->for($item)->create();

    expect($item->fresh()->updated_at->isToday())->toBeTrue();

    $item->update(['updated_at' => now()->subDay()]);

    $this->actingAs($user)
        ->deleteJson(route('api.items.photos.destroy', [$user->currentTeam, $item, $photo]))
        ->assertNoContent();

    expect($item->fresh()->updated_at->isToday())->toBeTrue();
});

test('photos of another team or another item are out of reach', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $otherItemPhoto = ItemPhoto::factory()->for(Item::factory()->for($user->currentTeam))->create();
    $otherTeamItem = Item::factory()->create();
    $otherTeamPhoto = ItemPhoto::factory()->for($otherTeamItem)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.items.photos.destroy', [$user->currentTeam, $item, $otherItemPhoto]))
        ->assertNotFound();

    $this->patchJson(route('api.items.photos.update', [$otherTeamItem->team, $otherTeamItem, $otherTeamPhoto]), ['position' => 0])
        ->assertForbidden();

    $this->deleteJson(route('api.items.photos.destroy', [$user->currentTeam, $otherTeamItem, $otherTeamPhoto]))
        ->assertNotFound();

    expect($otherItemPhoto->fresh())->not->toBeNull()
        ->and($otherTeamPhoto->fresh())->not->toBeNull();
});
