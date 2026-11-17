<?php

use App\Enums\TeamRole;
use App\Mcp\Tools\Inventory\MoveItemToTeam;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('it moves an item to another team of the user', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create(['name' => 'Cabin']);
    $user->teams()->attach($otherTeam, ['role' => TeamRole::Member]);
    $garage = Item::factory()->location()->for($user->currentTeam)->create();
    $item = Item::factory()->item()->childOf($garage)->create(['name' => 'Hammer']);

    SunnyTestServer::actingAs($user)
        ->tool(MoveItemToTeam::class, ['id' => $item->id, 'team_id' => $otherTeam->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $item->id)
            ->where('name', 'Hammer')
            ->where('type', 'item')
            ->where('team.id', $otherTeam->id)
            ->where('team.name', 'Cabin'));

    $item->refresh();

    expect($item->team_id)->toBe($otherTeam->id)
        ->and($item->parent_id)->toBeNull();
});

test('it moves a container together with its contents', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($otherTeam, ['role' => TeamRole::Member]);
    $toolbox = Item::factory()->bin()->for($user->currentTeam)->create();
    $tray = Item::factory()->bin()->childOf($toolbox)->create();
    $hammer = Item::factory()->item()->childOf($tray)->create();

    SunnyTestServer::actingAs($user)
        ->tool(MoveItemToTeam::class, ['id' => $toolbox->id, 'team_id' => $otherTeam->id])
        ->assertOk();

    expect($tray->fresh())->team_id->toBe($otherTeam->id)->parent_id->toBe($toolbox->id)
        ->and($hammer->fresh())->team_id->toBe($otherTeam->id)->parent_id->toBe($tray->id);
});

test('it requires an id and team_id', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(MoveItemToTeam::class)
        ->assertHasErrors(['id', 'team id']);
});

test('it cannot move an item to a team the user does not belong to', function () {
    $user = User::factory()->create();
    $strangerTeam = Team::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    SunnyTestServer::actingAs($user)
        ->tool(MoveItemToTeam::class, ['id' => $item->id, 'team_id' => $strangerTeam->id])
        ->assertHasErrors(['Team not found.']);

    expect($item->fresh()->team_id)->toBe($user->current_team_id);
});

test('it cannot move an item to the current team', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    SunnyTestServer::actingAs($user)
        ->tool(MoveItemToTeam::class, ['id' => $item->id, 'team_id' => $user->current_team_id])
        ->assertHasErrors(['Team not found.']);
});

test('it cannot move items from other teams', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create();
    $user->teams()->attach($otherTeam, ['role' => TeamRole::Member]);
    $item = Item::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(MoveItemToTeam::class, ['id' => $item->id, 'team_id' => $otherTeam->id])
        ->assertHasErrors(['Item not found.']);

    expect($item->fresh()->team_id)->not->toBe($otherTeam->id);
});
