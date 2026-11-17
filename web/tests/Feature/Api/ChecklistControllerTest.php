<?php

use App\Enums\ChecklistType;
use App\Enums\TeamRole;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Team;
use App\Models\User;

test('guests cannot access checklists', function () {
    $this->getJson(route('api.checklists.index', 'household'))->assertUnauthorized();
    $this->postJson(route('api.checklists.store', 'household'))->assertUnauthorized();
    $this->getJson(route('api.checklists.show', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.checklists.update', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.checklists.destroy', ['household', 1]))->assertUnauthorized();
    $this->postJson(route('api.checklists.clear-completed', ['household', 1]))->assertUnauthorized();
    $this->postJson(route('api.checklists.reset', ['household', 1]))->assertUnauthorized();
});

test('index returns checklists for the team with their items', function () {
    $user = User::factory()->create();
    $groceries = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries']);
    ChecklistItem::factory()->for($groceries)->count(2)->create();
    Checklist::factory()->for($user->currentTeam)->create(['name' => 'Chores']);
    Checklist::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.checklists.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.name', 'Chores')
        ->assertJsonPath('data.1.name', 'Groceries')
        ->assertJsonCount(2, 'data.1.items');
});

test('store creates a checklist and returns it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.store', $user->currentTeam), [
            'name' => 'Groceries',
            'type' => 'shopping',
            'user_id' => $user->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Groceries')
        ->assertJsonPath('data.type', 'shopping')
        ->assertJsonPath('data.user_id', $user->id);

    $this->assertDatabaseHas('checklists', [
        'team_id' => $user->current_team_id,
        'name' => 'Groceries',
        'type' => 'shopping',
    ]);
});

test('store returns the existing checklist when a client uuid is retried', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';

    $first = $this->actingAs($user)
        ->postJson(route('api.checklists.store', $user->currentTeam), ['name' => 'Groceries', 'type' => 'shopping', 'client_uuid' => $uuid])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.checklists.store', $user->currentTeam), ['name' => 'Groceries', 'type' => 'shopping', 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Checklist::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store returns the existing checklist when a retry wins the race to insert it', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $raced = createAfterLookup('checklists', 'client_uuid', fn () => Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries', 'type' => ChecklistType::Shopping, 'client_uuid' => $uuid]));

    $this->actingAs($user)
        ->postJson(route('api.checklists.store', $user->currentTeam), ['name' => 'Groceries', 'type' => 'shopping', 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $raced->model->id);

    expect(Checklist::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store validates the checklist', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.store', $user->currentTeam), ['type' => 'groceries'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'type']);
});

test('store rejects an owner who is not on the team', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.store', $user->currentTeam), ['name' => 'Chores', 'type' => 'todo', 'user_id' => $stranger->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');
});

test('store creates the checklist in the team from the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->postJson(route('api.checklists.store', $team), ['name' => 'Chores', 'type' => 'todo'])
        ->assertCreated();

    expect($team->checklists()->count())->toBe(1)
        ->and($user->currentTeam->checklists()->count())->toBe(0);
});

test('show returns a checklist with its items in order', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $second = ChecklistItem::factory()->for($checklist)->create(['position' => 2]);
    $first = ChecklistItem::factory()->for($checklist)->create(['position' => 1]);

    $this->actingAs($user)
        ->getJson(route('api.checklists.show', [$user->currentTeam, $checklist]))
        ->assertOk()
        ->assertJsonPath('data.id', $checklist->id)
        ->assertJsonPath('data.items.0.id', $first->id)
        ->assertJsonPath('data.items.1.id', $second->id);
});

test('show returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.checklists.show', [$checklist->team, $checklist]))
        ->assertForbidden();
});

test('show returns 404 when the checklist belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $checklist = Checklist::factory()->for($team)->create();

    $this->actingAs($user)
        ->getJson(route('api.checklists.show', [$user->currentTeam, $checklist]))
        ->assertNotFound();
});

test('update modifies a checklist', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->ownedBy($user)->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.update', [$user->currentTeam, $checklist]), [
            'name' => 'Birthday ideas',
            'type' => 'wishlist',
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Birthday ideas')
        ->assertJsonPath('data.type', 'wishlist')
        ->assertJsonPath('data.user_id', $user->id);

    expect($checklist->fresh())
        ->name->toBe('Birthday ideas')
        ->type->toBe(ChecklistType::Wishlist);
});

test('update can hand a checklist to the whole household', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->ownedBy($user)->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.update', [$user->currentTeam, $checklist]), ['user_id' => null])
        ->assertOk()
        ->assertJsonPath('data.user_id', null);
});

test('update returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.checklists.update', [$checklist->team, $checklist]), ['name' => 'Nope'])
        ->assertForbidden();
});

test('destroy deletes a checklist', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.checklists.destroy', [$user->currentTeam, $checklist]))
        ->assertNoContent();

    $this->assertSoftDeleted('checklists', ['id' => $checklist->id]);
});

test('destroy returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.checklists.destroy', [$checklist->team, $checklist]))
        ->assertForbidden();
});

test('clear completed removes the completed items and returns the list', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $milk = ChecklistItem::factory()->for($checklist)->create(['position' => 1]);
    $eggs = ChecklistItem::factory()->for($checklist)->completed($user)->create(['position' => 2]);
    $bread = ChecklistItem::factory()->for($checklist)->completed($user)->create(['position' => 3]);
    $elsewhere = ChecklistItem::factory()->completed()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.clear-completed', [$user->currentTeam, $checklist]))
        ->assertOk()
        ->assertJsonPath('data.id', $checklist->id)
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $milk->id);

    $this->assertModelMissing($eggs);
    $this->assertModelMissing($bread);
    $this->assertModelExists($elsewhere);
});

test('clear completed can be sent again', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    ChecklistItem::factory()->for($checklist)->completed()->create();
    $milk = ChecklistItem::factory()->for($checklist)->create();

    $this->actingAs($user)->postJson(route('api.checklists.clear-completed', [$user->currentTeam, $checklist]))->assertOk();

    $this->actingAs($user)
        ->postJson(route('api.checklists.clear-completed', [$user->currentTeam, $checklist]))
        ->assertOk()
        ->assertJsonCount(1, 'data.items')
        ->assertJsonPath('data.items.0.id', $milk->id);
});

test('cleared items drop out of the next sync', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    ChecklistItem::factory()->for($checklist)->completed()->create();
    $kept = ChecklistItem::factory()->for($checklist)->create();

    $this->actingAs($user)->postJson(route('api.checklists.clear-completed', [$user->currentTeam, $checklist]))->assertOk();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'checklist_items')
        ->assertJsonPath('checklist_items.0.id', $kept->id);
});

test('clear completed returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();
    $item = ChecklistItem::factory()->for($checklist)->completed()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.clear-completed', [$checklist->team, $checklist]))
        ->assertForbidden();

    $this->assertModelExists($item);
});

test('clear completed returns 404 when the checklist belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $checklist = Checklist::factory()->for($team)->create();
    $item = ChecklistItem::factory()->for($checklist)->completed()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.clear-completed', [$user->currentTeam, $checklist]))
        ->assertNotFound();

    $this->assertModelExists($item);
});

test('reset unticks every item and returns the list', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $socks = ChecklistItem::factory()->for($checklist)->completed($user)->create(['position' => 1]);
    $charger = ChecklistItem::factory()->for($checklist)->create(['position' => 2]);
    $elsewhere = ChecklistItem::factory()->completed()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.reset', [$user->currentTeam, $checklist]))
        ->assertOk()
        ->assertJsonPath('data.id', $checklist->id)
        ->assertJsonCount(2, 'data.items')
        ->assertJsonPath('data.items.0.id', $socks->id)
        ->assertJsonPath('data.items.0.completed_at', null)
        ->assertJsonPath('data.items.0.completed_by', null)
        ->assertJsonPath('data.items.1.id', $charger->id);

    expect($socks->fresh()->isCompleted())->toBeFalse()
        ->and($elsewhere->fresh()->isCompleted())->toBeTrue();
});

test('reset items are sent again in an incremental sync', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($checklist)->completed()->create(['updated_at' => now()->subWeek()]);

    $this->actingAs($user)->postJson(route('api.checklists.reset', [$user->currentTeam, $checklist]))->assertOk();

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertOk()
        ->assertJsonCount(1, 'checklist_items')
        ->assertJsonPath('checklist_items.0.id', $item->id)
        ->assertJsonPath('checklist_items.0.completed_at', null);
});

test('reset returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->create();
    $item = ChecklistItem::factory()->for($checklist)->completed()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.reset', [$checklist->team, $checklist]))
        ->assertForbidden();

    expect($item->fresh()->isCompleted())->toBeTrue();
});

test('reset returns 404 when the checklist belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $checklist = Checklist::factory()->for($team)->create();
    $item = ChecklistItem::factory()->for($checklist)->completed()->create();

    $this->actingAs($user)
        ->postJson(route('api.checklists.reset', [$user->currentTeam, $checklist]))
        ->assertNotFound();

    expect($item->fresh()->isCompleted())->toBeTrue();
});
