<?php

use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $team = Team::factory()->create([
        'name' => 'Straw Hats',
        'timezone' => 'Asia/Tokyo',
        'week_start' => Carbon::MONDAY,
    ]);

    $user = User::factory()->memberOf($team)->create();

    actingAs($user)
        ->get(route('kiosk.configure.settings'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->assertOk()
        ->assertSet('form.timezone', 'Asia/Tokyo')
        ->assertSet('form.week_start', Carbon::MONDAY);
})->group('smoke');

test('can change kiosk settings', function () {
    $team = Team::factory()->create([
        'name' => 'Straw Hats',
        'timezone' => 'Asia/Tokyo',
        'week_start' => Carbon::MONDAY,
    ]);

    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->set('form.timezone', 'America/Sao_Paulo')
        ->set('form.week_start', Carbon::SUNDAY)
        ->set('form.address', [
            'address' => '123 Grand Line',
            'city' => 'East Blue',
            'state' => 'GL',
            'zip' => '00001',
            'lat' => '0.0',
            'long' => '0.0',
        ])
        ->call('save')
        ->assertHasNoErrors();

    expect($team->fresh())
        ->timezone->toBe('America/Sao_Paulo')
        ->week_start->toBe(Carbon::SUNDAY);
});

test('options must be valid', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->set('form.timezone', 'Not/AZone')
        ->set('form.week_start', 15)
        ->call('save')
        ->assertHasErrors(['form.timezone', 'form.week_start']);
});
