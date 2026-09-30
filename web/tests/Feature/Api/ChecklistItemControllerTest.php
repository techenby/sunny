<?php

use App\Enums\TeamRole;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Team;
use App\Models\User;

test('guests cannot access checklist items', function () {
    $this->getJson(route('api.checklists.items.index', ['household', 1]))->assertUnauthorized();
    $this->postJson(route('api.checklists.items.store', ['household', 1]))->assertUnauthorized();
    $this->getJson(route('api.checklists.items.show', ['household', 1, 1]))->assertUnauthorized();
    $this->patchJson(route('api.checklists.items.update', ['household', 1, 1]))->assertUnauthorized();
    $this->deleteJson(route('api.checklists.items.destroy', ['household', 1, 1]))->assertUnauthorized();
});

test('index returns the checklist items in order', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $second = ChecklistItem::factory()->for($checklist)->create(['position' => 2]);
    $first = ChecklistItem::factory()->for($checklist)->create(['position' => 1]);
    ChecklistItem::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.checklists.items.index', [$user->currentTeam, $checklist]))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $first->id)
        ->assertJsonPath('data.1.id', $second->id);
});

test('store adds an item to the end of the checklist', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    ChecklistItem::factory()->for($checklist)->create(['position' => 3]);

    $this->actingAs($user)
        ->postJson(route('api.checklists.items.store', [$user->currentTeam, $checklist]), ['name' => 'Milk'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Milk')
        ->assertJsonPath('data.checklist_id', $checklist->id)
        ->assertJsonPath('data.position', 4)
        ->assertJsonPath('data.completed_at', null);
});

test('store can add an item that is already completed', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.items.store', [$user->currentTeam, $checklist]), ['name' => 'Milk', 'completed' => true])
        ->assertCreated()
        ->assertJsonPath('data.completed_by', $user->id)
        ->assertJsonPath('data.completed_at', fn ($value) => $value !== null);
});

test('store returns the existing item when a client uuid is retried', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';

    $first = $this->actingAs($user)
        ->postJson(route('api.checklists.items.store', [$user->currentTeam, $checklist]), ['name' => 'Milk', 'client_uuid' => $uuid])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.checklists.items.store', [$user->currentTeam, $checklist]), ['name' => 'Milk', 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(ChecklistItem::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store validates the item name', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.items.store', [$user->currentTeam, $checklist]), ['name' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('store returns 404 for a deleted checklist', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $checklist->delete();

    $this->actingAs($user)
        ->postJson(route('api.checklists.items.store', [$user->currentTeam, $checklist]), ['name' => 'Milk'])
        ->assertNotFound();
});

test('show returns an item', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->getJson(route('api.checklists.items.show', [$user->currentTeam, $item->checklist, $item]))
        ->assertOk()
        ->assertJsonPath('data.id', $item->id)
        ->assertJsonPath('data.name', $item->name);
});

test('show returns 404 when the item belongs to a different checklist than the url', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->getJson(route('api.checklists.items.show', [$user->currentTeam, $checklist, $item]))
        ->assertNotFound();
});

test('show returns 404 when the checklist belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($team))->create();

    $this->actingAs($user)
        ->getJson(route('api.checklists.items.show', [$user->currentTeam, $item->checklist, $item]))
        ->assertNotFound();
});

test('show returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.checklists.items.show', [$item->checklist->team, $item->checklist, $item]))
        ->assertForbidden();
});

test('update renames an item', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.items.update', [$user->currentTeam, $item->checklist, $item]), ['name' => 'Oat milk'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Oat milk');
});

test('update completes an item and records who completed it', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.items.update', [$user->currentTeam, $item->checklist, $item]), ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.completed_by', $user->id)
        ->assertJsonPath('data.completed_at', fn ($value) => $value !== null);

    expect($item->fresh()->isCompleted())->toBeTrue();
});

test('update keeps the original completion when an item is completed again', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->completed($other)->create(['completed_at' => now()->subDay()]);

    $this->actingAs($user)
        ->patchJson(route('api.checklists.items.update', [$user->currentTeam, $item->checklist, $item]), ['completed' => true])
        ->assertOk()
        ->assertJsonPath('data.completed_by', $other->id);

    expect($item->fresh()->completed_at->isYesterday())->toBeTrue();
});

test('update uncompletes an item', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->completed($user)->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.items.update', [$user->currentTeam, $item->checklist, $item]), ['completed' => false])
        ->assertOk()
        ->assertJsonPath('data.completed_at', null)
        ->assertJsonPath('data.completed_by', null);
});

test('update leaves completion alone when it is not sent', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->completed($user)->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.items.update', [$user->currentTeam, $item->checklist, $item]), ['name' => 'Bread'])
        ->assertOk();

    expect($item->fresh()->isCompleted())->toBeTrue();
});

test('update validates the item', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.items.update', [$user->currentTeam, $item->checklist, $item]), ['name' => '', 'completed' => 'maybe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'completed']);
});

test('update returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.items.update', [$item->checklist->team, $item->checklist, $item]), ['completed' => true])
        ->assertForbidden();
});

test('destroy deletes an item', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->for(Checklist::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->deleteJson(route('api.checklists.items.destroy', [$user->currentTeam, $item->checklist, $item]))
        ->assertNoContent();

    $this->assertModelMissing($item);
});

test('destroy returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.checklists.items.destroy', [$item->checklist->team, $item->checklist, $item]))
        ->assertForbidden();
});
