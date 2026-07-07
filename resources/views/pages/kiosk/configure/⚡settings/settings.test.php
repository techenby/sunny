<?php

use App\Enums\Appearance;
use App\Models\KioskDevice;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertModelExists;
use function Pest\Laravel\assertModelMissing;

test('renders successfully', function () {
    $team = Team::factory()->create([
        'name' => 'Straw Hats',
        'timezone' => 'Asia/Tokyo',
        'week_start' => Carbon::MONDAY,
        'appearance' => Appearance::Light,
    ]);

    $user = User::factory()->memberOf($team)->create();

    actingAs($user)
        ->get(route('kiosk.configure.settings'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->assertOk()
        ->assertSet('form.timezone', 'Asia/Tokyo')
        ->assertSet('form.week_start', Carbon::MONDAY)
        ->assertSet('form.appearance', 'light');
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
        ->set('form.appearance', 'light')
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
        ->week_start->toBe(Carbon::SUNDAY)
        ->appearance->toBe(Appearance::Light);
});

test('options must be valid', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->set('form.timezone', 'Not/AZone')
        ->set('form.week_start', 15)
        ->set('form.appearance', 'sepia')
        ->call('save')
        ->assertHasErrors(['form.timezone', 'form.week_start', 'form.appearance']);
});

describe('device management', function () {
    test('can forget a paired display for the current team', function () {
        $user = User::factory()->create();

        $kitchen = KioskDevice::factory()->paired($user, $user->currentTeam)->create(['name' => 'Kitchen']);
        $bedroom = KioskDevice::factory()->paired($user, $user->currentTeam)->create(['name' => 'Bedroom']);

        Livewire::actingAs($user)
            ->test('pages::kiosk.configure.settings')
            ->assertSee('Kitchen')
            ->assertSee('Bedroom')
            ->call('forget', $kitchen->id)
            ->assertDontSee('Kitchen')
            ->assertSee('Bedroom');

        assertModelMissing($kitchen);
        assertModelExists($bedroom);
    });

    test('cannot forget device from another team', function () {
        $user = User::factory()->create();
        $other = KioskDevice::factory()->paired()->create();

        Livewire::actingAs($user)
            ->test('pages::kiosk.configure.settings')
            ->call('forget', $other->id);

        assertModelExists($other);
    });
});
