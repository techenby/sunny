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
        'rotation' => 90,
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
        ->assertSet('form.appearance', 'light')
        ->assertSet('form.rotation', 90);
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
        ->set('form.rotation', 270)
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
        ->appearance->toBe(Appearance::Light)
        ->rotation->toBe(270);
});

test('options must be valid', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->set('form.timezone', 'Not/AZone')
        ->set('form.week_start', 15)
        ->set('form.appearance', 'sepia')
        ->set('form.rotation', 45)
        ->call('save')
        ->assertHasErrors(['form.timezone', 'form.week_start', 'form.appearance', 'form.rotation']);
});

$withAddress = ['address' => [
    'address' => '123 Grand Line',
    'city' => 'East Blue',
    'state' => 'GL',
    'zip' => '00001',
    'lat' => '0.0',
    'long' => '0.0',
]];

test('can change idle settings', function () use ($withAddress) {
    $team = Team::factory()->create($withAddress);
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->assertSet('form.screensaver_after', 5)
        ->assertSet('form.return_home_after', 10)
        ->assertSet('form.night_mode', false)
        ->set('form.screensaver_after', 2)
        ->set('form.return_home_after', 0)
        ->set('form.night_mode', true)
        ->set('form.night_starts_at', '21:30')
        ->set('form.night_ends_at', '06:15')
        ->call('save')
        ->assertHasNoErrors();

    expect($team->fresh())
        ->screensaver_after->toBe(2)
        ->return_home_after->toBe(0)
        ->night_starts_at->toBe('21:30')
        ->night_ends_at->toBe('06:15');
});

test('turning off night mode clears its hours', function () use ($withAddress) {
    $team = Team::factory()->create([...$withAddress, 'night_starts_at' => '22:00', 'night_ends_at' => '06:00']);
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->assertSet('form.night_mode', true)
        ->set('form.night_mode', false)
        ->set('form.night_starts_at', '')
        ->call('save')
        ->assertHasNoErrors();

    expect($team->fresh())
        ->night_starts_at->toBeNull()
        ->night_ends_at->toBeNull();
});

test('idle options must be valid', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.settings')
        ->set('form.screensaver_after', 7)
        ->set('form.return_home_after', 3)
        ->set('form.night_mode', true)
        ->set('form.night_starts_at', 'late')
        ->set('form.night_ends_at', '')
        ->call('save')
        ->assertHasErrors(['form.screensaver_after', 'form.return_home_after', 'form.night_starts_at', 'form.night_ends_at']);
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
