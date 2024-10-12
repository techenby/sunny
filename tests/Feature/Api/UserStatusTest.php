<?php

use App\Models\User;

test('can add status of user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/user/status', [
            'status' => 'pairing',
        ])
        ->assertStatus(200);

    expect($user->fresh()->status)->toBe('pairing');
});

test('can change status of user', function () {
    $user = User::factory()->create(['status' => 'meeting']);

    $this->actingAs($user)
        ->postJson('/api/user/status', [
            'status' => 'pairing',
        ])
        ->assertStatus(200);

    expect($user->fresh()->status)->toBe('pairing');
});

test('status is required', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson('/api/user/status', [
            // 'status' => 'pairing',
        ])
        ->assertJsonValidationErrorFor('status');

    expect($user->fresh()->status)->not->toBe('pairing');
});

test('status can be cleared', function () {
    $user = User::factory()->create(['status' => 'pairing']);

    $this->actingAs($user)
        ->deleteJson('/api/user/status')
        ->assertOk();

    expect($user->fresh()->status)->toBeNull();
});
