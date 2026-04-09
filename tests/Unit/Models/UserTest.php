<?php

use App\Enums\TeamRole;
use App\Models\User;

test('can safely delete user', function () {
    $newgate = User::factory()->create(['name' => 'Edward Newgate']);
    $whitebeard = $newgate->currentTeam;

    $ace = User::factory()->create(['name' => 'Portgas D. Ace']);
    $spade = $ace->currentTeam;

    $whitebeard->memberships()->create(['user_id' => $ace->id, 'role' => TeamRole::Member]);

    $deuce = User::factory()->create(['name' => 'Masked Deuce']);
    $deuce->teams->firstWhere('name', "Masked Deuce's Team")->delete();
    $spade->memberships()->create(['user_id' => $deuce->id, 'role' => TeamRole::Member]);
    $whitebeard->memberships()->create(['user_id' => $deuce->id, 'role' => TeamRole::Member]);
    $deuce->switchTeam($spade);

    expect($ace->teams)->toHaveCount(2)
        ->and($deuce->fresh()->teams)->toHaveCount(2)
        ->and($newgate->teams)->toHaveCount(1)
        ->and($whitebeard->members)->toHaveCount(3);

    $ace->purge();

    expect($ace->fresh())->toBeNull()
        ->and($spade->fresh())->deleted_at->not->toBeNull()
        ->and($deuce->fresh()->teams->pluck('name'))->toContain("Edward Newgate's Team")
        ->and($whitebeard->fresh()->members->pluck('name'))->toHaveCount(2)->toContain('Edward Newgate')->toContain('Masked Deuce');
});
