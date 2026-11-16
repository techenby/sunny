<?php

use App\Models\User;

test('existing users are marked as onboarded so they never see set-up', function () {
    $user = User::factory()->needsOnboarding()->create(['created_at' => now()->subMonth()]);

    (require database_path('migrations/2026_11_16_180000_add_onboarding_to_users_table.php'))->down();
    (require database_path('migrations/2026_11_16_180000_add_onboarding_to_users_table.php'))->up();

    $user->refresh();

    expect($user->onboarded_at->equalTo($user->created_at))->toBeTrue()
        ->and($user->needsOnboarding())->toBeFalse();
});
