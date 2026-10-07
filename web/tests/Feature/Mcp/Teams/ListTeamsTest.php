<?php

use App\Enums\TeamRole;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Teams\ListTeams;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it lists the teams the user belongs to with their role', function () {
    $user = User::factory()->create(['name' => 'Luffy']);
    $crew = Team::factory()->create(['name' => 'Straw Hats']);
    $crew->members()->attach($user, ['role' => TeamRole::Admin->value]);

    SunnyServer::actingAs($user)
        ->tool(ListTeams::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 2)
            ->where('current_team_id', $user->current_team_id)
            ->has('teams', 2)
            ->has('teams.0', fn (AssertableJson $team) => $team
                ->where('name', "Luffy's Team")
                ->where('is_personal', true)
                ->where('role', TeamRole::Owner->value)
                ->where('is_current', true)
                ->etc())
            ->has('teams.1', fn (AssertableJson $team) => $team
                ->where('id', $crew->id)
                ->where('name', 'Straw Hats')
                ->where('slug', $crew->slug)
                ->where('is_personal', false)
                ->where('role', TeamRole::Admin->value)
                ->where('is_current', false)));
});

test('it does not list teams the user is not on', function () {
    $user = User::factory()->create();
    Team::factory()->create(['name' => 'Marines']);

    SunnyServer::actingAs($user)
        ->tool(ListTeams::class)
        ->assertOk()
        ->assertDontSee('Marines');
});
