<?php

use App\Mcp\Tools\Teams\ListKioskDevices;
use App\Models\KioskDevice;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('it lists paired devices for the current team, most recently seen first', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $older = KioskDevice::factory()->paired($user, $team)->create([
        'name' => 'Galley',
        'user_agent' => 'Mozilla/5.0 (iPad)',
        'last_seen_at' => now()->subDay(),
    ]);
    $recent = KioskDevice::factory()->paired($user, $team)->create([
        'name' => 'Aquarium Bar',
        'last_seen_at' => now(),
    ]);
    KioskDevice::factory()->pending()->create();
    KioskDevice::factory()->paired()->create(['name' => 'Marine HQ']);

    SunnyTestServer::actingAs($user)
        ->tool(ListKioskDevices::class)
        ->assertOk()
        ->assertDontSee(['Marine HQ', $older->uuid, $older->last_ip])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 2)
            ->where('devices.0.id', $recent->id)
            ->where('devices.0.name', 'Aquarium Bar')
            ->has('devices.1', fn (AssertableJson $device) => $device
                ->where('id', $older->id)
                ->where('name', 'Galley')
                ->where('user_agent', 'Mozilla/5.0 (iPad)')
                ->where('paired_at', $older->paired_at->toIso8601String())
                ->where('last_seen_at', $older->last_seen_at->toIso8601String())));
});

test('it returns an empty list when no devices are paired', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(ListKioskDevices::class)
        ->assertOk()
        ->assertStructuredContent(['count' => 0, 'devices' => []]);
});
