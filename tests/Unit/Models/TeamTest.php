<?php

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
