<?php

use App\Enums\TeamRole;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Teams\ForgetKioskDevice;
use App\Models\KioskDevice;
use App\Models\Team;
use App\Models\User;

test('it forgets a paired device on the current team', function () {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired($user, $user->currentTeam)->create(['name' => 'Galley']);

    SunnyServer::actingAs($user)
        ->tool(ForgetKioskDevice::class, ['id' => $device->id])
        ->assertOk()
        ->assertSee("Kiosk device \"Galley\" (ID {$device->id}) forgotten.");

    expect(KioskDevice::find($device->id))->toBeNull();
});

test('it cannot forget a device on another team', function () {
    $user = User::factory()->create();
    $device = KioskDevice::factory()->paired()->create();

    SunnyServer::actingAs($user)
        ->tool(ForgetKioskDevice::class, ['id' => $device->id])
        ->assertHasErrors(['Kiosk device not found.']);

    expect(KioskDevice::find($device->id))->not->toBeNull();
});

test('members without the update permission cannot forget devices', function () {
    $team = Team::factory()->create();
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $user->switchTeam($team);
    $device = KioskDevice::factory()->paired($user, $team)->create();

    SunnyServer::actingAs($user)
        ->tool(ForgetKioskDevice::class, ['id' => $device->id])
        ->assertHasErrors(['This action is unauthorized.']);

    expect(KioskDevice::find($device->id))->not->toBeNull();
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ForgetKioskDevice::class)
        ->assertHasErrors(['You must provide the id of the kiosk device to forget.']);
});
