<?php

use App\Models\User;

test('tokens that never expire are given a 30 day expiration', function () {
    $this->freezeSecond();
    $user = User::factory()->create();
    $neverExpires = $user->createToken('Sunny Mobile')->accessToken;
    $expires = $user->createToken('Raycast', ['*'], now()->addDays(90))->accessToken;

    (require database_path('migrations/2026_09_24_005119_expire_never_expiring_personal_access_tokens.php'))->up();

    expect($neverExpires->fresh()->expires_at->equalTo(now()->addDays(30)))->toBeTrue()
        ->and($expires->fresh()->expires_at->equalTo(now()->addDays(90)))->toBeTrue();
});
