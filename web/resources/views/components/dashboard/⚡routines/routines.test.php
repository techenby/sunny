<?php

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Models\Routine;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\User;
use Livewire\Livewire;

test('it shows my routines and household routines due today', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $other = User::factory()->memberOf($team)->create();

    foreach ([
        'Get ready' => Routine::factory()->for($team)->daily()->assignedTo($user),
        'Chores' => Routine::factory()->for($team)->daily()->household(),
        'Homework' => Routine::factory()->for($team)->daily()->assignedTo($other),
    ] as $name => $factory) {
        RoutineStep::factory()->for($factory->create(['name' => $name]))->create(['name' => "{$name} step"]);
    }

    Livewire::actingAs($user)
        ->test('dashboard.routines')
        ->assertSee('Get ready')
        ->assertSee('Get ready step')
        ->assertSee('Chores')
        ->assertDontSee('Homework');
});

test('it does not show inactive routines or another team\'s routines', function () {
    $user = User::factory()->create();
    Routine::factory()->for($user->currentTeam)->daily()->inactive()->create(['name' => 'Paused']);
    Routine::factory()->daily()->household()->create(['name' => 'Someone else']);

    Livewire::actingAs($user)
        ->test('dashboard.routines')
        ->assertDontSee('Paused')
        ->assertDontSee('Someone else')
        ->assertSee(__('Nothing scheduled for you today.'));
});

test('toggling a step completes it for the user', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->household()->create();
    RoutineStep::factory()->for($routine)->create();

    $component = Livewire::actingAs($user)->test('dashboard.routines');
    $step = RoutineOccurrenceStep::sole();

    $component->call('toggle', $step->id);

    expect($step->fresh()->isCompleted())->toBeTrue()
        ->and($step->fresh()->completed_by)->toBe($user->id);
});

test('it will not toggle a step from another team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->daily()->create();
    RoutineStep::factory()->for($routine)->create();

    $component = Livewire::actingAs($user)->test('dashboard.routines');
    resolve(GenerateRoutineOccurrences::class)->forDate($routine->team);

    $component->call('toggle', RoutineOccurrenceStep::sole()->id)->assertForbidden();
});

test('steps follow the routine order, even after one is completed', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->household()->create();
    RoutineStep::factory()->for($routine)->create(['name' => 'Second', 'position' => 2]);
    RoutineStep::factory()->for($routine)->create(['name' => 'First', 'position' => 1]);

    $component = Livewire::actingAs($user)->test('dashboard.routines');
    $first = RoutineOccurrenceStep::whereRelation('step', 'name', 'First')->sole();

    $component->call('toggle', $first->id)->assertSeeInOrder(['First', 'Second']);

    expect($component->get('occurrences')->first()->steps->map(fn ($step) => $step->step->name)->all())
        ->toBe(['First', 'Second']);
});
