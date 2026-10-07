<?php

use App\Http\Middleware\TrackLastActivity;
use App\Models\KioskDevice;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

test('web requests record when the user was last active', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->last_active_at)->not->toBeNull();
});

test('api requests record when the user was last active', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->getJson(route('api.user'))->assertOk();

    expect($user->fresh()->last_active_at)->not->toBeNull();
});

test('activity is only written once per throttle window', function () {
    $recently = now()->subMinutes(TrackLastActivity::THROTTLE_MINUTES - 1)->startOfSecond();
    $user = User::factory()->create(['last_active_at' => $recently]);

    $this->actingAs($user)->get(route('dashboard'))->assertOk();

    expect($user->fresh()->last_active_at->equalTo($recently))->toBeTrue();

    $this->travel(2)->minutes();
    $this->actingAs($user->fresh())->get(route('dashboard'))->assertOk();

    expect($user->fresh()->last_active_at->isAfter($recently))->toBeTrue();
});

test('kiosk sessions do not count as user activity', function () {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired($user, $user->currentTeam)->create();

    $this->actingAs($user)
        ->withSession(['kiosk_device_id' => $device->id])
        ->get(route('kiosk.calendar', ['current_team' => $user->currentTeam->slug]));

    expect($user->fresh()->last_active_at)->toBeNull();
});
