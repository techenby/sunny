<?php

use App\Enums\TeamRole;
use App\Models\Recipe;
use App\Models\Team;
use App\Models\User;

test('purge deletes team and clears current team references', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $member = User::factory()->create();
    $team->memberships()->create(['user_id' => $member->id, 'role' => TeamRole::Member]);
    $member->switchTeam($team);

    $team->purge();

    expect(Team::find($team->id))->toBeNull()
        ->and($user->fresh()->current_team_id)->toBeNull()
        ->and($member->fresh()->current_team_id)->toBeNull();
});

test('purge deletes recipes from team', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->create();
    $teamId = $user->currentTeam->id;

    $user->currentTeam->purge();

    expect(Recipe::where('team_id', $teamId)->count())->toBe(0);
});

test('owner returns user with owner role', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    expect($team->owner()->id)->toBe($user->id);
});

test('slug is auto-generated on creation', function () {
    $team = Team::create(['name' => 'My Awesome Team']);

    expect($team->slug)->toBe('my-awesome-team');
});

test('slug is unique', function () {
    Team::create(['name' => 'Duplicate']);
    $team2 = Team::create(['name' => 'Duplicate']);

    expect($team2->slug)->toBe('duplicate-1');
});

test('slug updates when name changes', function () {
    $team = Team::create(['name' => 'Original']);

    $team->update(['name' => 'Updated']);

    expect($team->fresh()->slug)->toBe('updated');
});
