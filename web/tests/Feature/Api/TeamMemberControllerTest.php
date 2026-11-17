<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->luffy = User::factory()->create(['name' => 'Luffy']);
    $this->nami = User::factory()->create(['name' => 'Nami']);
    $this->zoro = User::factory()->create(['name' => 'Zoro']);
    $this->crew = Team::factory()->create(['name' => 'Straw Hats']);
    $this->crew->memberships()->create(['user_id' => $this->luffy->id, 'role' => TeamRole::Owner]);
    $this->crew->memberships()->create(['user_id' => $this->nami->id, 'role' => TeamRole::Admin]);
    $this->crew->memberships()->create(['user_id' => $this->zoro->id, 'role' => TeamRole::Member]);
});

test('guests cannot manage members', function () {
    $this->patchJson(route('api.teams.members.update', ['household', 1]), ['role' => 'admin'])->assertUnauthorized();
    $this->deleteJson(route('api.teams.members.destroy', ['household', 1]))->assertUnauthorized();
});

test('the owner can change a member\'s role', function () {
    $this->actingAs($this->luffy)
        ->patchJson(route('api.teams.members.update', [$this->crew, $this->zoro]), ['role' => 'admin'])
        ->assertOk()
        ->assertJsonPath('data.members.2', ['id' => $this->zoro->id, 'name' => 'Zoro', 'initials' => 'Z', 'role' => 'admin']);

    expect($this->zoro->teamRole($this->crew))->toBe(TeamRole::Admin);
});

test('admins and members cannot change roles', function (string $user) {
    $this->actingAs($this->{$user})
        ->patchJson(route('api.teams.members.update', [$this->crew, $this->zoro]), ['role' => 'admin'])
        ->assertForbidden();

    expect($this->zoro->teamRole($this->crew))->toBe(TeamRole::Member);
})->with(['nami', 'zoro']);

test('nobody can be made an owner by changing their role', function () {
    $this->actingAs($this->luffy)
        ->patchJson(route('api.teams.members.update', [$this->crew, $this->zoro]), ['role' => 'owner'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('role');

    expect($this->zoro->teamRole($this->crew))->toBe(TeamRole::Member);
});

test('the owner\'s own role cannot be changed', function () {
    $this->actingAs($this->luffy)
        ->patchJson(route('api.teams.members.update', [$this->crew, $this->luffy]), ['role' => 'member'])
        ->assertNotFound();

    expect($this->luffy->teamRole($this->crew))->toBe(TeamRole::Owner);
});

test('the owner can remove a member', function (string $user) {
    $this->{$user}->switchTeam($this->crew);

    $this->actingAs($this->luffy)
        ->deleteJson(route('api.teams.members.destroy', [$this->crew, $this->{$user}]))
        ->assertNoContent();

    expect($this->{$user}->fresh())
        ->belongsToTeam($this->crew)->toBeFalse()
        ->current_team_id->toBe($this->{$user}->personalTeam()->id);
})->with(['nami', 'zoro']);

test('admins and members cannot remove anyone', function (string $user) {
    $this->actingAs($this->{$user})
        ->deleteJson(route('api.teams.members.destroy', [$this->crew, $this->zoro]))
        ->assertForbidden();

    expect($this->zoro->belongsToTeam($this->crew))->toBeTrue();
})->with(['nami', 'zoro']);

test('the owner cannot be removed', function () {
    $this->actingAs($this->luffy)
        ->deleteJson(route('api.teams.members.destroy', [$this->crew, $this->luffy]))
        ->assertNotFound();

    expect($this->luffy->belongsToTeam($this->crew))->toBeTrue();
});

test('people outside the team cannot be changed or removed through it', function () {
    $usopp = User::factory()->create();

    $this->actingAs($this->luffy)
        ->patchJson(route('api.teams.members.update', [$this->crew, $usopp]), ['role' => 'admin'])
        ->assertNotFound();

    $this->actingAs($this->luffy)
        ->deleteJson(route('api.teams.members.destroy', [$this->crew, $usopp]))
        ->assertNotFound();
});

test('another team\'s owner cannot manage this team\'s members', function () {
    $this->actingAs(User::factory()->create())
        ->deleteJson(route('api.teams.members.destroy', [$this->crew, $this->zoro]))
        ->assertForbidden();

    expect($this->zoro->belongsToTeam($this->crew))->toBeTrue();
});
