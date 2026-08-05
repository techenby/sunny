<?php

use App\Enums\RoutineCadence;
use App\Models\Routine;
use App\Models\RoutineTask;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\assertModelMissing;

test('renders successfully', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()
        ->for($team)
        ->has(RoutineTask::factory()->state(['name' => 'Pack lunch', 'order' => 0]), 'tasks')
        ->create(['name' => 'School morning']);
    $user = User::factory()->memberOf($team)->create();

    actingAs($user)
        ->get(route('kiosk.configure.routines'))
        ->assertOk()
        ->assertSee('School morning')
        ->assertSee('Pack lunch');

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.routines')
        ->assertOk()
        ->assertSee($routine->name)
        ->assertSee('Pack lunch');
})->group('smoke');

test('can create a weekly routine with ordered tasks', function () {
    $team = Team::factory()->create();
    $user = User::factory()->memberOf($team)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.routines')
        ->call('add')
        ->set('form.name', 'Sunday reset')
        ->set('form.cadence', RoutineCadence::Weekly->value)
        ->set('form.reset_weekday', 0)
        ->set('form.tasks.0.name', 'Plan meals')
        ->call('addTask')
        ->set('form.tasks.1.name', 'Review calendar')
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.name', '')
        ->assertSet('form.cadence', RoutineCadence::Daily->value);

    $routine = Routine::query()->where('team_id', $team->id)->firstOrFail();

    expect($routine)
        ->name->toBe('Sunday reset')
        ->cadence->toBe(RoutineCadence::Weekly)
        ->reset_weekday->toBe(0)
        ->and($routine->tasks()->pluck('name')->all())
        ->toBe(['Plan meals', 'Review calendar']);
});

test('can edit a routine and synchronize its tasks', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()
        ->for($team)
        ->has(
            RoutineTask::factory()->count(2)->sequence(
                ['name' => 'Brush teeth', 'order' => 0],
                ['name' => 'Pack backpack', 'order' => 1],
            ),
            'tasks',
        )
        ->create(['name' => 'School morning']);
    $user = User::factory()->memberOf($team)->create();
    $removedTask = $routine->tasks()->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.routines')
        ->call('edit', $routine->id)
        ->assertSet('form.name', 'School morning')
        ->call('removeTask', 0)
        ->set('form.name', 'Weekday morning')
        ->set('form.tasks.0.name', 'Pack school bag')
        ->call('addTask')
        ->set('form.tasks.1.name', 'Fill water bottle')
        ->call('save')
        ->assertHasNoErrors();

    expect($routine->fresh())
        ->name->toBe('Weekday morning')
        ->and($routine->tasks()->pluck('name')->all())
        ->toBe(['Pack school bag', 'Fill water bottle']);

    assertModelMissing($removedTask);
});

test('daily routines do not keep a reset weekday', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.routines')
        ->call('add')
        ->set('form.name', 'Bedtime')
        ->set('form.cadence', RoutineCadence::Daily->value)
        ->set('form.reset_weekday', 4)
        ->set('form.tasks.0.name', 'Brush teeth')
        ->call('save')
        ->assertHasNoErrors();

    expect($user->currentTeam->routines()->firstOrFail()->reset_weekday)->toBeNull();
});

test('can remove a routine and its tasks', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()
        ->for($user->currentTeam)
        ->has(RoutineTask::factory(), 'tasks')
        ->create();
    $task = $routine->tasks()->firstOrFail();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.routines')
        ->call('delete', $routine->id)
        ->assertDontSee($routine->name);

    assertModelMissing($routine);
    assertModelMissing($task);
});

test('validates routine input', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.routines')
        ->call('add')
        ->set('form.name', '')
        ->set('form.cadence', RoutineCadence::Weekly->value)
        ->set('form.reset_weekday', 7)
        ->set('form.tasks.0.name', '')
        ->call('save')
        ->assertHasErrors([
            'form.name',
            'form.reset_weekday',
            'form.tasks.0.name',
        ]);
});

test('cannot edit a routine from another team', function () {
    $user = User::factory()->create();
    $otherRoutine = Routine::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.routines')
        ->call('edit', $otherRoutine->id);
})->throws(ModelNotFoundException::class);

test('cannot delete a routine from another team', function () {
    $user = User::factory()->create();
    $otherRoutine = Routine::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.configure.routines')
        ->call('delete', $otherRoutine->id);
})->throws(ModelNotFoundException::class);
