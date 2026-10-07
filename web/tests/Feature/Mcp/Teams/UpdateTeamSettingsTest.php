<?php

use App\Enums\Appearance;
use App\Enums\TeamRole;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Teams\UpdateTeamSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;

$fullAddress = [
    'address' => '1 Sunny Deck',
    'city' => 'Water 7',
    'state' => 'GL',
    'zip' => '00007',
    'lat' => '35.6762',
    'long' => '139.6503',
];

test('it updates only the provided settings', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class, [
            'timezone' => 'Asia/Tokyo',
            'week_start' => Carbon::MONDAY,
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $team->id)
            ->where('timezone', 'Asia/Tokyo')
            ->where('week_start', Carbon::MONDAY)
            ->where('week_start_day', 'Monday')
            ->where('appearance', 'dark')
            ->where('rotation', 0)
            ->where('address', null)
            ->etc());

    expect($team->fresh())
        ->timezone->toBe('Asia/Tokyo')
        ->week_start->toBe(Carbon::MONDAY)
        ->appearance->toBe(Appearance::Dark)
        ->rotation->toBe(0);
});

test('it updates appearance and rotation', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class, ['appearance' => 'light', 'rotation' => 270])
        ->assertOk();

    expect($user->currentTeam->fresh())
        ->appearance->toBe(Appearance::Light)
        ->rotation->toBe(270);
});

test('it sets a full address', function () use ($fullAddress) {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class, ['address' => [...$fullAddress, 'lat' => 35.6762, 'long' => 139.6503]])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('address', $fullAddress)
            ->etc());

    expect($user->currentTeam->fresh()->address)->toBe($fullAddress);
});

test('it merges a partial address into the existing address', function () use ($fullAddress) {
    $user = User::factory()->create();
    $user->currentTeam->update(['address' => $fullAddress]);

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class, ['address' => ['city' => 'Enies Lobby']])
        ->assertOk();

    expect($user->currentTeam->fresh()->address)->toBe([...$fullAddress, 'city' => 'Enies Lobby']);
});

test('it rejects a partial address when the team has no address yet', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class, ['address' => ['city' => 'Water 7']])
        ->assertHasErrors(['The address is incomplete. Also provide: address, state, zip, lat, long.']);

    expect($user->currentTeam->fresh()->address)->toBeNull();
});

test('it validates the settings', function (array $arguments, string $message) {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class, $arguments)
        ->assertHasErrors([$message]);
})->with([
    'timezone' => [['timezone' => 'Grand Line/Raftel'], 'The timezone must be a valid IANA timezone'],
    'week start' => [['week_start' => 7], 'The week_start must be between 0 (Sunday) and 6 (Saturday).'],
    'appearance' => [['appearance' => 'neon'], 'The appearance must be one of: light, dark, system.'],
    'rotation' => [['rotation' => 45], 'The rotation must be one of: 0, 90, 180, 270.'],
    'latitude' => [['address' => ['lat' => 'north']], 'address.lat'],
]);

test('it requires at least one field', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class)
        ->assertHasErrors(['Provide at least one field to update']);
});

test('members without the update permission cannot change settings', function () {
    $team = Team::factory()->create(['timezone' => 'America/Chicago']);
    $user = User::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $user->switchTeam($team);

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class, ['timezone' => 'Asia/Tokyo'])
        ->assertHasErrors(['This action is unauthorized.']);

    expect($team->fresh()->timezone)->toBe('America/Chicago');
});

test('it does not change other teams', function () {
    $user = User::factory()->create();
    $otherTeam = Team::factory()->create(['timezone' => 'America/Chicago']);

    SunnyServer::actingAs($user)
        ->tool(UpdateTeamSettings::class, ['timezone' => 'Asia/Tokyo'])
        ->assertOk();

    expect($otherTeam->fresh()->timezone)->toBe('America/Chicago');
});
