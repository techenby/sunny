<?php

use App\Enums\Appearance;
use App\Mcp\Tools\Teams\GetTeamSettings;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('it returns the current team settings', function () {
    $team = Team::factory()->create([
        'name' => 'Straw Hats',
        'timezone' => 'Asia/Tokyo',
        'week_start' => Carbon::MONDAY,
        'appearance' => Appearance::Light,
        'rotation' => 90,
        'address' => [
            'address' => '1 Sunny Deck',
            'city' => 'Water 7',
            'state' => 'GL',
            'zip' => '00007',
            'lat' => '35.6762',
            'long' => '139.6503',
        ],
    ]);
    $user = User::factory()->memberOf($team)->create();

    SunnyTestServer::actingAs($user)
        ->tool(GetTeamSettings::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $team->id)
            ->where('name', 'Straw Hats')
            ->where('timezone', 'Asia/Tokyo')
            ->where('week_start', Carbon::MONDAY)
            ->where('week_start_day', 'Monday')
            ->where('appearance', 'light')
            ->where('rotation', 90)
            ->where('address', [
                'address' => '1 Sunny Deck',
                'city' => 'Water 7',
                'state' => 'GL',
                'zip' => '00007',
                'lat' => '35.6762',
                'long' => '139.6503',
            ]));
});

test('it returns a null address when none is set', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(GetTeamSettings::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('week_start', Carbon::SUNDAY)
            ->where('week_start_day', 'Sunday')
            ->where('address', null)
            ->etc());
});

test('it does not return settings for other teams', function () {
    $user = User::factory()->create();
    Team::factory()->create(['timezone' => 'Europe/Paris']);

    SunnyTestServer::actingAs($user)
        ->tool(GetTeamSettings::class)
        ->assertOk()
        ->assertDontSee('Europe/Paris');
});
