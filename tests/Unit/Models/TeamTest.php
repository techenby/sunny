<?php

use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;

test('has user returns true for member', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->users()->attach($user);

    expect($team->hasUser($user))->toBeTrue();
});

test('has user returns false for non-member', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();

    expect($team->hasUser($user))->toBeFalse();
});

test('remove user detaches member', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->users()->attach($user);

    $team->removeUser($user);

    expect($team->fresh()->users)->toHaveCount(0);
});

test('purge deletes team and clears current team references', function () {
    $team = Team::factory()->create();
    $owner = $team->owner;
    $owner->switchTeam($team);

    $member = User::factory()->create();
    $team->users()->attach($member);
    $member->switchTeam($team);

    $team->purge();

    expect(Team::find($team->id))->toBeNull()
        ->and($owner->fresh()->current_team_id)->toBeNull()
        ->and($member->fresh()->current_team_id)->toBeNull();
});

test('purge deletes recipes from team', function () {
    $user = User::factory()->withTeam()->create();
    Recipe::factory()->for($user->currentTeam)->create();
    $teamId = $user->currentTeam->id;

    $user->currentTeam->purge();

    expect(Recipe::where('team_id', $teamId)->count())->toBe(0);
});

test('slug is automatically generated from name on creation', function () {
    $team = Team::factory()->create(['name' => 'My Awesome Team']);

    expect($team->slug)->toBe('my-awesome-team');
});

test('slug is updated when name changes', function () {
    $team = Team::factory()->create(['name' => 'Original Name']);

    $team->update(['name' => 'Updated Name']);

    expect($team->fresh()->slug)->toBe('updated-name');
});

test('slug is unique when duplicate names exist', function () {
    $teamA = Team::factory()->create(['name' => 'Same Name']);
    $teamB = Team::factory()->create(['name' => 'Same Name']);

    expect($teamA->slug)->toBe('same-name');
    expect($teamB->slug)->toBe('same-name-1');
});

test('slug does not change when updating non-name attributes', function () {
    $team = Team::factory()->create(['name' => 'Stable Team']);

    $team->update(['name' => 'Stable Team']);

    expect($team->fresh()->slug)->toBe('stable-team');
});

test('slug uniqueness handles multiple collisions', function () {
    Team::factory()->create(['name' => 'Duplicate']);
    Team::factory()->create(['name' => 'Duplicate']);
    $third = Team::factory()->create(['name' => 'Duplicate']);

    expect($third->slug)->toBe('duplicate-2');
});
