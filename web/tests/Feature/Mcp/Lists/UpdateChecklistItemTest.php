<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\UpdateChecklistItem;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it completes an item and records who completed it', function () {
    $this->freezeSecond();

    $user = User::factory()->create(['name' => 'Andy']);
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($checklist)->create(['name' => 'Milk', 'position' => 1]);

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklistItem::class, ['checklist_id' => $checklist->id, 'item_id' => $item->id, 'completed' => true])
        ->assertOk()
        ->assertStructuredContent([
            'id' => $item->id,
            'name' => 'Milk',
            'position' => 1,
            'completed' => true,
            'completed_at' => now()->toIso8601String(),
            'completed_by_name' => 'Andy',
        ]);

    $item->refresh();

    expect($item->isCompleted())->toBeTrue()
        ->and($item->completed_by)->toBe($user->id);
});

test('it uncompletes an item', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($checklist)->completed($user)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklistItem::class, ['checklist_id' => $checklist->id, 'item_id' => $item->id, 'completed' => false])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('completed', false)
            ->where('completed_at', null)
            ->where('completed_by_name', null)
            ->etc());

    $item->refresh();

    expect($item->isCompleted())->toBeFalse()
        ->and($item->completed_by)->toBeNull();
});

test('it renames an item without changing its completion', function () {
    $user = User::factory()->create();
    $other = User::factory()->memberOf($user->currentTeam)->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($checklist)->completed($other)->create(['name' => 'Milk']);

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklistItem::class, [
            'checklist_id' => $checklist->id,
            'item_id' => $item->id,
            'name' => 'Oat milk',
            'completed' => true,
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('name', 'Oat milk')
            ->where('completed', true)
            ->where('completed_by_name', $other->name)
            ->etc());

    expect($item->refresh()->name)->toBe('Oat milk')
        ->and($item->completed_by)->toBe($other->id);
});

test('it requires a field to update', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($checklist)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklistItem::class, ['checklist_id' => $checklist->id, 'item_id' => $item->id])
        ->assertHasErrors(['Provide a name, completed, or both to update.']);
});

test('it validates the completed flag', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($checklist)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklistItem::class, ['checklist_id' => $checklist->id, 'item_id' => $item->id, 'completed' => 'yes'])
        ->assertHasErrors(['The completed field must be true or false.']);
});

test('it does not update items on another checklist', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();
    $otherChecklist = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($otherChecklist)->create(['name' => 'Milk']);

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklistItem::class, ['checklist_id' => $checklist->id, 'item_id' => $item->id, 'name' => 'Eggs'])
        ->assertHasErrors(['Checklist item not found.']);

    expect($item->refresh()->name)->toBe('Milk');
});

test('it does not update items from other teams', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create(['name' => 'Milk']);

    SunnyServer::actingAs($user)
        ->tool(UpdateChecklistItem::class, ['checklist_id' => $item->checklist_id, 'item_id' => $item->id, 'completed' => true])
        ->assertHasErrors(['Checklist not found.']);

    expect($item->refresh()->isCompleted())->toBeFalse();
});
