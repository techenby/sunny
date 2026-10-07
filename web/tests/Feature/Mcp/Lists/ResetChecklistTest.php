<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\ResetChecklist;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it marks every item incomplete', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Chores']);
    ChecklistItem::factory()->for($checklist)->completed($user)->count(2)->create();
    ChecklistItem::factory()->for($checklist)->create();

    SunnyServer::actingAs($user)
        ->tool(ResetChecklist::class, ['id' => $checklist->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $checklist->id)
            ->where('item_count', 3)
            ->where('completed_count', 0)
            ->where('progress', 0)
            ->has('items', 3)
            ->where('items.0.completed', false)
            ->where('items.0.completed_at', null)
            ->where('items.0.completed_by_name', null)
            ->etc());

    expect($checklist->items()->completed()->count())->toBe(0);
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ResetChecklist::class)
        ->assertHasErrors();
});

test('it does not reset checklists from other teams', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->completed()->create();

    SunnyServer::actingAs($user)
        ->tool(ResetChecklist::class, ['id' => $item->checklist_id])
        ->assertHasErrors(['Checklist not found.']);

    expect($item->refresh()->isCompleted())->toBeTrue();
});
