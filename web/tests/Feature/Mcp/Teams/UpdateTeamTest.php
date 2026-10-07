<?php

use App\Enums\TeamRole;
use App\Mcp\Tools\Teams\UpdateTeam;
use App\Models\Team;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('it renames the current team', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    SunnyTestServer::actingAs($user)
        ->tool(UpdateTeam::class, ['name' => 'Thousand Sunny'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $team->id)
            ->where('name', 'Thousand Sunny')
            ->where('slug', 'thousand-sunny')
            ->where('is_personal', true)
            ->where('role', TeamRole::Owner->value)
            ->where('is_current', true));

    expect($team->fresh())
        ->name->toBe('Thousand Sunny')
        ->slug->toBe('thousand-sunny');
});

test('admins can rename the team', function () {
    $team = Team::factory()->create(['name' => 'Straw Hats']);
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Admin->value]);
    $user->switchTeam($team);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateTeam::class, ['name' => 'Straw Hat Grand Fleet'])
        ->assertOk();

    expect($team->fresh()->name)->toBe('Straw Hat Grand Fleet');
});

test('members without the update permission cannot rename the team', function () {
    $team = Team::factory()->create(['name' => 'Straw Hats']);
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $user->switchTeam($team);

    SunnyTestServer::actingAs($user)
        ->tool(UpdateTeam::class, ['name' => 'Hijacked'])
        ->assertHasErrors(['This action is unauthorized.']);

    expect($team->fresh()->name)->toBe('Straw Hats');
});

test('it rejects reserved team names', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(UpdateTeam::class, ['name' => 'Settings'])
        ->assertHasErrors(['This team name is reserved and cannot be used.']);
});

test('it requires a name', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(UpdateTeam::class)
        ->assertHasErrors(['You must provide a new name for the team.']);
});
