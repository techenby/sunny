<?php

use App\Models\User;
use Illuminate\Support\Carbon;

test('user status is cleared after two hours of inactivity', function () {
    $user = User::factory()->create(['status' => 'pairing']);

    Carbon::setTestNow(now()->addHours(2));

    $this->artisan('app:clear-status');

    expect($user->fresh()->status)->toBeNull();
});

test('user status is cleared after more than two hours of inactivity', function () {
    $user = User::factory()->create(['status' => 'pairing']);

    Carbon::setTestNow(now()->addHours(2)->addMinute());

    $this->artisan('app:clear-status');

    expect($user->fresh()->status)->toBeNull();
});

test('user status is not cleared before two hours of inactivity', function () {
    $user = User::factory()->create(['status' => 'pairing']);

    Carbon::setTestNow(now()->addHour());

    $this->artisan('app:clear-status');

    expect($user->fresh()->status)->toBe('pairing');
});

test('does not update null status', function () {
    $user = User::factory()->create(['status' => null]);
    $oldUpdatedAt = $user->updated_at->toDateTimeString();

    Carbon::setTestNow(now()->addHours(2));

    $this->artisan('app:clear-status');

    expect($user->fresh()->updated_at->toDateTimeString())->toBe($oldUpdatedAt);
});
