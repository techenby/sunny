<?php

use App\Models\User;

test('can get first name of user', function () {
    $user = User::factory()->make(['name' => 'Andy Newhouse']);

    expect($user->firstName)->toBe('Andy');
});
