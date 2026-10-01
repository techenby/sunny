<?php

use App\Enums\TeamRole;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;

test('guests cannot access sync', function () {
    $this->getJson(route('api.sync'))->assertUnauthorized();
});

test('returns all teams, recipes, and items for the user', function () {
    $user = User::factory()->create();
    Recipe::factory()->for($user->currentTeam)->count(2)->create();
    Item::factory()->for($user->currentTeam)->count(3)->create();

    Recipe::factory()->count(2)->create();
    Item::factory()->count(2)->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'teams')
        ->assertJsonCount(2, 'recipes')
        ->assertJsonCount(3, 'items')
        ->assertJsonStructure(['teams', 'recipes', 'items', 'routine_occurrences', 'synced_at']);
});

test('returns only records updated since the given timestamp', function () {
    $user = User::factory()->create();

    [$old, $new] = Recipe::factory()
        ->for($user->currentTeam)
        ->count(2)
        ->sequence(
            ['updated_at' => now()->subDays(2)],
            ['updated_at' => now()->subHour()]
        )
        ->create();

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertOk()
        ->assertJsonCount(1, 'recipes')
        ->assertJsonPath('recipes.0.id', $new->id);
});

test('includes soft-deleted records', function () {
    $user = User::factory()->create();
    $recipe = Recipe::factory()->for($user->currentTeam)->create();
    $recipe->delete();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'recipes')
        ->assertJsonPath('recipes.0.id', $recipe->id)
        ->assertJsonPath('recipes.0.deleted_at', fn ($value) => $value !== null);
});

test('includes data from all user teams', function () {
    $user = User::factory()->create();
    $secondTeam = Team::factory()->create();
    $user->teams()->attach($secondTeam, ['role' => TeamRole::Member]);

    Recipe::factory()->for($user->currentTeam)->create();
    Recipe::factory()->for($secondTeam)->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(2, 'teams')
        ->assertJsonCount(2, 'recipes');
});

test('validates since parameter is a valid date', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => 'not-a-date']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors('since');
});

test('includes routines due today and tomorrow in each team', function () {
    Date::setTestNow('2026-09-30 12:00:00');
    $user = User::factory()->create();
    $secondTeam = Team::factory()->create();
    $user->teams()->attach($secondTeam, ['role' => TeamRole::Member]);

    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();
    RoutineStep::factory()->for($routine)->create(['name' => 'Feed the cat']);
    Routine::factory()->for($secondTeam)->weekly([Carbon::THURSDAY])->create();
    Routine::factory()->daily()->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(3, 'routine_occurrences')
        ->assertJsonPath('routine_occurrences.0.due_on', '2026-09-30')
        ->assertJsonPath('routine_occurrences.0.routine.team_id', $user->current_team_id)
        ->assertJsonPath('routine_occurrences.0.steps.0.name', 'Feed the cat')
        ->assertJsonPath('routine_occurrences.1.due_on', '2026-10-01')
        ->assertJsonPath('routine_occurrences.2.routine.team_id', $secondTeam->id)
        ->assertJsonPath('routine_occurrences.2.due_on', '2026-10-01');
});

test('includes todays routines even when syncing incrementally', function () {
    $user = User::factory()->create();
    Routine::factory()->for($user->currentTeam)->daily()->create(['updated_at' => now()->subWeek()]);

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertOk()
        ->assertJsonCount(2, 'routine_occurrences');
});

test('returns routines with their steps and assignee for the users teams', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->assignedTo($user)->daily()->create();
    RoutineStep::factory()->for($routine)->create(['name' => 'Feed the cat']);
    Routine::factory()->daily()->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonStructure(['teams', 'recipes', 'items', 'routines', 'routine_occurrences', 'synced_at'])
        ->assertJsonCount(1, 'routines')
        ->assertJsonPath('routines.0.id', $routine->id)
        ->assertJsonPath('routines.0.team_id', $user->current_team_id)
        ->assertJsonPath('routines.0.user.id', $user->id)
        ->assertJsonPath('routines.0.steps.0.name', 'Feed the cat');
});

test('returns only routines updated since the given timestamp', function () {
    $user = User::factory()->create();

    [, $new] = Routine::factory()
        ->for($user->currentTeam)
        ->count(2)
        ->sequence(
            ['updated_at' => now()->subDays(2)],
            ['updated_at' => now()->subHour()]
        )
        ->create();

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertOk()
        ->assertJsonCount(1, 'routines')
        ->assertJsonPath('routines.0.id', $new->id);
});

test('a step change brings its routine back into an incremental sync', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create(['updated_at' => now()->subWeek()]);
    $step = RoutineStep::factory()->for($routine)->create();
    DB::table('routines')->where('id', $routine->id)->update(['updated_at' => now()->subWeek()]);

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertJsonCount(0, 'routines');

    $step->delete();

    $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertJsonCount(1, 'routines')
        ->assertJsonCount(0, 'routines.0.steps');
});

test('includes soft-deleted routines but not their occurrences', function () {
    Date::setTestNow('2026-09-30 12:00:00');
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();

    $this->actingAs($user)->getJson(route('api.sync'))->assertJsonCount(2, 'routine_occurrences');

    $routine->delete();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'routines')
        ->assertJsonPath('routines.0.id', $routine->id)
        ->assertJsonPath('routines.0.deleted_at', fn ($value) => $value !== null)
        ->assertJsonCount(0, 'routine_occurrences');
});

test('inactive routines stay in routines but leave the upcoming occurrences', function () {
    Date::setTestNow('2026-09-30 12:00:00');
    $user = User::factory()->create();
    Routine::factory()->for($user->currentTeam)->daily()->inactive()->create();

    $this->actingAs($user)
        ->getJson(route('api.sync'))
        ->assertOk()
        ->assertJsonCount(1, 'routines')
        ->assertJsonPath('routines.0.is_active', false)
        ->assertJsonCount(0, 'routine_occurrences');
});
