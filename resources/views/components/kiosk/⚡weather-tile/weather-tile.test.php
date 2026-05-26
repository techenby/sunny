<?php

use App\Http\Integrations\OpenWeather\Requests\OneCall;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Saloon;

test('renders weather from api', function () {
    Saloon::fake([
        OneCall::class => MockResponse::make([
            'current' => [
                'temp' => 63.4,
                'weather' => [['description' => 'overcast clouds', 'icon' => '04d']],
            ],
            'daily' => [
                ['temp' => ['max' => 73.2, 'min' => 55.1]],
            ],
        ]),
    ]);

    $team = Team::factory()->create([
        'address' => [
            'address' => '123 Main St',
            'city' => 'Chicago',
            'state' => 'IL',
            'zip' => '60601',
            'lat' => '41.8781',
            'long' => '-87.6298',
        ],
    ]);

    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('kiosk.weather-tile')
        ->assertSee('Chicago')
        ->assertSee('63°')
        ->assertSee('73°')
        ->assertSee('55°');

    Saloon::assertSent(OneCall::class);
});

test('renders nothing without address coordinates', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('kiosk.weather-tile')
        ->assertDontSee('°');
});
