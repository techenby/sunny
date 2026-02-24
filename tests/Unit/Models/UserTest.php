<?php

use App\Models\User;

test('can safely delete user', function () {
    $newgate = User::factory()->withTeam('Whitebeard Pirates')->create(['name' => 'Edward Newgate']);
    $whitebeard = $newgate->teams->first();

    $ace = User::factory()
        ->withTeam('Spade Pirates')
        ->hasAttached($whitebeard)
        ->create(['name' => 'Portgas D. Ace']);
    $spade = $ace->teams->firstWhere('name', 'Spade Pirates');

    $deuce = User::factory()->hasAttached($spade)->hasAttached($whitebeard)->create(['name' => 'Masked Deuce']);

    expect($ace->teams)->toHaveCount(2)
        ->and($deuce->teams)->toHaveCount(2)
        ->and($newgate->teams)->toHaveCount(1)
        ->and($whitebeard->users)->toHaveCount(3);

    $ace->purge();

    expect($ace->fresh())->toBeNull()
        ->and($spade->fresh())->toBeNull()
        ->and($deuce->fresh()->teams->pluck('name'))->toHaveOne()->toContain('Whitebeard Pirates')
        ->and($whitebeard->fresh()->users->pluck('name'))->toHaveCount(2)->toContain('Edward Newgate')->toContain('Masked Deuce');
});
