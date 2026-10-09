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
        ->test('kiosk.weather-tile', ['team' => $team])
        ->assertSee('Chicago')
        ->assertSee('63°')
        ->assertSee('73°')
        ->assertSee('55°');

    Saloon::assertSent(OneCall::class);
});

test('shows skeleton when api returns 429', function () {
    Saloon::fake([
        OneCall::class => MockResponse::make(
            ['cod' => 429, 'message' => 'Too Many Requests'],
            429,
        ),
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
        ->test('kiosk.weather-tile', ['team' => $team])
        ->assertDontSee('°')
        ->assertSee('shimmer');

    Saloon::assertSent(OneCall::class);
});

test('renders nothing without address coordinates', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('kiosk.weather-tile', ['team' => $user->currentTeam])
        ->assertDontSee('°');
});

test('picks up new weather when it polls after the cache expires', function () {
    $weather = fn (float $temp): MockResponse => MockResponse::make([
        'current' => [
            'temp' => $temp,
            'weather' => [['description' => 'clear sky', 'icon' => '01d']],
        ],
        'daily' => [
            ['temp' => ['max' => 80.0, 'min' => 50.0]],
        ],
    ]);

    Saloon::fake([OneCall::class => $weather(63.4)]);

    $team = Team::factory()->create([
        'address' => ['city' => 'Chicago', 'lat' => '41.8781', 'long' => '-87.6298'],
    ]);
    $user = User::factory()->memberOf($team)->create();

    $component = Livewire::actingAs($user)
        ->test('kiosk.weather-tile', ['team' => $team])
        ->assertSeeHtml('wire:poll.900s')
        ->assertSee('63°');

    Saloon::fake([OneCall::class => $weather(71.2)]);
    $this->travel(31)->minutes();

    $component->call('$refresh')->assertSee('71°');
});
