<?php

use App\Models\Crew;
use App\Models\CrewInvitation;
use App\Models\User;

test('has user returns true for member', function () {
    $crew = Crew::factory()->create();
    $user = User::factory()->create();
    $crew->users()->attach($user);

    expect($crew->hasUser($user))->toBeTrue();
});

test('has user returns false for non-member', function () {
    $crew = Crew::factory()->create();
    $user = User::factory()->create();

    expect($crew->hasUser($user))->toBeFalse();
});

test('remove user detaches member', function () {
    $crew = Crew::factory()->create();
    $user = User::factory()->create();
    $crew->users()->attach($user);

    $crew->removeUser($user);

    expect($crew->fresh()->users)->toHaveCount(0);
});

test('purge deletes crew and clears current crew references', function () {
    $crew = Crew::factory()->create();
    $owner = $crew->owner;
    $owner->switchCrew($crew);

    $member = User::factory()->create();
    $crew->users()->attach($member);
    $member->switchCrew($crew);

    $crew->purge();

    expect(Crew::find($crew->id))->toBeNull()
        ->and($owner->fresh()->current_crew_id)->toBeNull()
        ->and($member->fresh()->current_crew_id)->toBeNull();
});
