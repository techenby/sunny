<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSeeLivewire('dashboard.routines')
        ->assertSeeLivewire('dashboard.events')
        ->assertSeeLivewire('dashboard.lists')
        ->assertSeeLivewire('dashboard.recipes')
        ->assertSeeLivewire('dashboard.items');
})->group('smoke');

test('it greets the user for the time of day in the team timezone', function (string $now, string $greeting) {
    Date::setTestNow($now);

    $team = Team::factory()->create(['timezone' => 'America/Chicago']);
    $user = User::factory()->memberOf($team)->create(['name' => 'Sam']);

    Livewire::actingAs($user)
        ->test('pages::dashboard')
        ->assertSee("{$greeting}, Sam");
})->with([
    'morning' => ['2026-05-08 13:00:00', 'Good morning'],
    'afternoon' => ['2026-05-08 19:00:00', 'Good afternoon'],
    'evening' => ['2026-05-09 01:00:00', 'Good evening'],
]);
