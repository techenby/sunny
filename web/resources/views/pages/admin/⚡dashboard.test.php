<?php

use App\Enums\TeamRole;
use App\Models\Item;
use App\Models\KioskDevice;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('admin.dashboard'))
        ->assertRedirect(route('login'));
});

test('authenticated users cannot visit the admin dashboard in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create())
        ->get(route('admin.dashboard'))
        ->assertForbidden();
});

test('Andy can visit the admin dashboard in production', function () {
    app()->detectEnvironment(fn () => 'production');

    $this->actingAs(User::factory()->create(['email' => 'andy@techenby.com']))
        ->get(route('admin.dashboard'))
        ->assertOk();
});

test('it counts new and active users', function () {
    User::factory()->create(['created_at' => now()->subDays(60), 'last_active_at' => now()->subDays(20)]);
    User::factory()->create(['created_at' => now()->subDays(10), 'last_active_at' => now()->subDays(2)]);
    User::factory()->create(['last_active_at' => null]);

    Livewire::test('pages::admin.dashboard')
        ->assertViewHas('userCount', 3)
        ->assertViewHas('newUserCount', 2)
        ->assertViewHas('weeklyActiveCount', 1)
        ->assertViewHas('monthlyActiveCount', 2)
        ->assertViewHas('signups', fn (array $signups) => count($signups) === 31
            && collect($signups)->sum('signups') === 2
            && collect($signups)->firstWhere('date', now()->subDays(10)->toDateString())['signups'] === 1);
});

test('it ranks teams by activity in the last 30 days without revealing their names', function () {
    $busy = Team::factory()->create(['name' => 'The Busy Household']);
    $quiet = Team::factory()->create(['name' => 'The Quiet Household']);
    Team::factory()->create();

    Item::factory()->for($busy)->count(3)->create();
    Recipe::factory()->for($busy)->create();
    Item::factory()->for($quiet)->create();
    Item::factory()->for($quiet)->count(5)->create(['created_at' => now()->subDays(45)]);

    $routine = Routine::factory()->for($quiet)->create(['created_at' => now()->subDays(45)]);
    $occurrence = RoutineOccurrence::factory()->for($routine)->create();
    RoutineStep::factory()->for($routine)->count(2)->create(['created_at' => now()->subDays(45)])
        ->each(fn (RoutineStep $step) => RoutineOccurrenceStep::factory()
            ->for($occurrence, 'occurrence')
            ->for($step, 'step')
            ->completed()
            ->create());

    Livewire::test('pages::admin.dashboard')
        ->assertSee('3 items')
        ->assertSee('1 recipe')
        ->assertDontSee('1 recipes')
        ->assertSee('2 completed steps')
        ->assertDontSee('The Busy Household')
        ->assertDontSee('The Quiet Household')
        ->assertViewHas('mostActiveTeams', function ($teams) {
            return $teams->count() === 2
                && $teams[0]['total'] === 4
                && $teams[0]['breakdown']['item'] === 3
                && $teams[0]['breakdown']['recipe'] === 1
                && $teams[1]['total'] === 3
                && $teams[1]['breakdown']['completed step'] === 2
                && str_starts_with($teams[0]['id'], 'T-');
        });
});

test('it gives each team a stable identifier across cards', function () {
    $user = User::factory()->create(['last_active_at' => now()]);
    Item::factory()->for($user->currentTeam)->create();

    $component = Livewire::test('pages::admin.dashboard');

    expect($component->viewData('mostActiveTeams')->first()['id'])
        ->toBe($component->viewData('recentlyActiveTeams')->first()['id'])
        ->toMatch('/^T-[0-9a-f]{6}$/');
});

test('it orders teams by their most recently active member', function () {
    $stale = User::factory()->create(['last_active_at' => now()->subDays(3)]);
    $fresh = User::factory()->create(['last_active_at' => now()->subMinutes(5)]);
    $neverActive = User::factory()->create(['last_active_at' => null]);

    $fresh->currentTeam->members()->attach($stale, ['role' => TeamRole::Member->value]);
    $fresh->currentTeam->members()->attach($neverActive, ['role' => TeamRole::Member->value]);

    Livewire::test('pages::admin.dashboard')
        ->assertViewHas('recentlyActiveTeams', fn ($teams) => $teams->count() === 2
            && $teams[0]['members'] === 3
            && $teams[0]['last_active_at']->isSameMinute(now()->subMinutes(5))
            && $teams[1]['members'] === 1);
});

test('it reports the share of teams using each feature', function () {
    [$first, $second, $third, $fourth] = User::factory()->count(4)->create()->map->currentTeam;

    Item::factory()->for($first)->count(2)->create();
    Item::factory()->for($second)->create();
    Recipe::factory()->for($first)->create();
    Recipe::factory()->for($third)->create()->delete();
    KioskDevice::factory()->paired($fourth->owner(), $fourth)->create();

    Livewire::test('pages::admin.dashboard')
        ->assertViewHas('featureAdoption', fn ($features) => $features['Inventory'] === ['teams' => 2, 'percent' => 50]
            && $features['Recipes'] === ['teams' => 1, 'percent' => 25]
            && $features['Kiosks'] === ['teams' => 1, 'percent' => 25]
            && $features['Routines'] === ['teams' => 0, 'percent' => 0]);
});

test('the signups chart uses whole number ticks', function () {
    Livewire::test('pages::admin.dashboard')
        ->assertViewHas('signupTicks', [0, 1]);

    User::factory()->count(9)->create();

    Livewire::test('pages::admin.dashboard')
        ->assertViewHas('signupTicks', [0, 3, 6, 9]);
});
