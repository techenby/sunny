<?php

use App\Enums\TeamRole;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Teams\SwitchTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it switches the current team', function () {
    $user = User::factory()->create();
    $crew = Team::factory()->create(['name' => 'Straw Hats']);
    $crew->members()->attach($user, ['role' => TeamRole::Member->value]);

    SunnyServer::actingAs($user)
        ->tool(SwitchTeam::class, ['team_id' => $crew->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $crew->id)
            ->where('name', 'Straw Hats')
            ->where('slug', $crew->slug)
            ->where('is_personal', false)
            ->where('role', TeamRole::Member->value)
            ->where('is_current', true));

    expect($user->fresh()->current_team_id)->toBe($crew->id);
});

test('it cannot switch to a team the user is not on', function () {
    $user = User::factory()->create();
    $originalTeamId = $user->current_team_id;
    $otherTeam = Team::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(SwitchTeam::class, ['team_id' => $otherTeam->id])
        ->assertHasErrors(['Team not found.']);

    expect($user->fresh()->current_team_id)->toBe($originalTeamId);
});

test('it requires a team id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(SwitchTeam::class)
        ->assertHasErrors(['You must provide the team_id']);
});
