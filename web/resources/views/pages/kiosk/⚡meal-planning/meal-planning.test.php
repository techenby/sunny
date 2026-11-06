<?php

use App\Enums\TeamRole;
use App\Models\KioskDevice;
use App\Models\MealPlanSlot;
use App\Models\MealTemplateItem;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

test('renders successfully', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('kiosk.meal-planning'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::kiosk.meal-planning')
        ->assertOk();
})->group('smoke');

test('it shows this week from the template and per-date changes, with today highlighted', function () {
    travelTo(Date::parse('2026-11-04 12:00:00', 'America/Chicago')); // Wednesday

    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['week_start' => Carbon::MONDAY, 'meals_saved_through' => '2026-11-01']);
    $dinner = $team->mealSlots()->firstWhere('name', 'Dinner');
    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 2, 'name' => 'Taco Tuesday']);
    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 3, 'name' => 'Curry']);
    MealPlanSlot::factory()->for($dinner, 'slot')->create(['date' => '2026-11-04'])->items()->create(['name' => 'Pizza night']);

    $component = Livewire::actingAs($user)->test('pages::kiosk.meal-planning');

    expect($component->instance()->days->map(fn (array $day) => $day['date']->toDateString())->all())
        ->toBe(['2026-11-02', '2026-11-03', '2026-11-04', '2026-11-05', '2026-11-06', '2026-11-07', '2026-11-08']);

    $component
        ->assertSee('Taco Tuesday')
        ->assertSee('Pizza night')
        ->assertDontSee('Curry')
        ->assertSeeHtml('aria-current="date"');
});

test('a paired kiosk shows its own team\'s meals, not the user\'s current team', function () {
    $user = User::factory()->create();
    $kioskTeam = Team::factory()->create();
    $user->teams()->attach($kioskTeam, ['role' => TeamRole::Member]);
    $device = KioskDevice::factory()->paired($user, $kioskTeam)->create();
    session(['kiosk_device_id' => $device->id]);

    $today = $kioskTeam->today()->dayOfWeek;
    MealTemplateItem::factory()->for($kioskTeam->mealSlots()->first(), 'slot')->create(['weekday' => $today, 'name' => 'Kiosk pancakes']);
    MealTemplateItem::factory()->for($user->currentTeam->mealSlots()->first(), 'slot')->create(['weekday' => $today, 'name' => 'Home waffles']);

    Livewire::actingAs($user)
        ->test('pages::kiosk.meal-planning')
        ->assertSee('Kiosk pancakes')
        ->assertDontSee('Home waffles');
});
