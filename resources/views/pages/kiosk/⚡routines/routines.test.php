<?php

use App\Enums\TimeOfDay;
use App\Models\Routine;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('kiosk.routines'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::kiosk.routines')
        ->assertOk();
})->group('smoke');

/**
 * @return array{0: User, 1: Team}
 */
function routineBoardUser(): array
{
    $user = User::factory()->create();

    return [$user, $user->currentTeam];
}

test('it shows a column per person and a household column', function () {
    [$user, $team] = routineBoardUser();
    $other = User::factory()->memberOf($team)->create(['name' => 'Alice']);

    $mine = Routine::factory()->for($team)->daily()->assignedTo($user)->create(['name' => 'Morning']);
    $theirs = Routine::factory()->for($team)->daily()->assignedTo($other)->create(['name' => 'Evening']);
    $shared = Routine::factory()->for($team)->daily()->household()->create(['name' => 'Chores']);

    foreach ([$mine, $theirs, $shared] as $routine) {
        RoutineStep::factory()->for($routine)->create();
    }

    $columns = Livewire::actingAs($user)
        ->test('pages::kiosk.routines')
        ->get('columns');

    expect(collect($columns)->pluck('name')->all())
        ->toBe(['Alice', $user->name, __('Household')]);
});

test('a column counts completed steps', function () {
    [$user, $team] = routineBoardUser();
    $routine = Routine::factory()->for($team)->daily()->assignedTo($user)->create();
    RoutineStep::factory()->for($routine)->count(4)->create();

    $component = Livewire::actingAs($user)->test('pages::kiosk.routines');

    expect($component->get('columns')[0]['total'])->toBe(4)
        ->and($component->get('columns')[0]['completed'])->toBe(0);

    $step = RoutineOccurrenceStep::first();
    $component->call('toggle', $step->id);

    expect($component->get('columns')[0]['completed'])->toBe(1);
});

test('toggling a step records the user and can be undone', function () {
    [$user, $team] = routineBoardUser();
    $routine = Routine::factory()->for($team)->daily()->household()->create();
    RoutineStep::factory()->for($routine)->create();

    $component = Livewire::actingAs($user)->test('pages::kiosk.routines');
    $step = RoutineOccurrenceStep::sole();

    $component->call('toggle', $step->id);
    expect($step->fresh()->isCompleted())->toBeTrue()
        ->and($step->fresh()->completed_by)->toBe($user->id);

    $component->call('toggle', $step->id);
    expect($step->fresh()->isCompleted())->toBeFalse();
});

test('it will not toggle a step from another team', function () {
    [$user] = routineBoardUser();
    $routine = Routine::factory()->daily()->create();
    RoutineStep::factory()->for($routine)->create();

    app(App\Actions\Routines\GenerateRoutineOccurrences::class)->handle($routine->team);
    $step = RoutineOccurrenceStep::sole();

    Livewire::actingAs($user)
        ->test('pages::kiosk.routines')
        ->call('toggle', $step->id)
        ->assertForbidden();

    expect($step->fresh()->isCompleted())->toBeFalse();
});

test('occurrences are ordered by time of day', function () {
    [$user, $team] = routineBoardUser();

    foreach ([TimeOfDay::Evening, TimeOfDay::Morning, TimeOfDay::Afternoon] as $timeOfDay) {
        $routine = Routine::factory()->for($team)->daily()->household()
            ->timeOfDay($timeOfDay)
            ->create(['name' => $timeOfDay->value]);

        RoutineStep::factory()->for($routine)->create();
    }

    $columns = Livewire::actingAs($user)->test('pages::kiosk.routines')->get('columns');

    expect($columns[0]['occurrences']->pluck('routine.name')->all())
        ->toBe(['morning', 'afternoon', 'evening']);
});

test('navigating changes the day and keeps completions apart', function () {
    [$user, $team] = routineBoardUser();
    $routine = Routine::factory()->for($team)->daily()->household()->create([
        'starts_on' => now()->subMonth(),
    ]);
    RoutineStep::factory()->for($routine)->create();

    $component = Livewire::actingAs($user)->test('pages::kiosk.routines');
    $component->call('toggle', RoutineOccurrenceStep::sole()->id);

    expect($component->get('columns')[0]['completed'])->toBe(1);

    $component->call('previous');

    expect($component->get('isToday'))->toBeFalse()
        ->and($component->get('columns')[0]['completed'])->toBe(0);

    $component->call('current');

    expect($component->get('isToday'))->toBeTrue()
        ->and($component->get('columns')[0]['completed'])->toBe(1);
});

test('a day with no routines shows the empty state', function () {
    [$user, $team] = routineBoardUser();
    Routine::factory()->for($team)->weekly([Carbon::MONDAY])->create([
        'starts_on' => now()->subMonth(),
    ]);

    Livewire::actingAs($user)
        ->test('pages::kiosk.routines', ['focusedDate' => '2026-08-11']) // Tuesday
        ->assertSee(__('Nothing to do'));
});

test('a paused routine drops off the board', function () {
    [$user, $team] = routineBoardUser();
    $routine = Routine::factory()->for($team)->daily()->household()->create();
    RoutineStep::factory()->for($routine)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.routines')
        ->assertSee($routine->name);

    $routine->update(['is_active' => false]);

    Livewire::actingAs($user)
        ->test('pages::kiosk.routines')
        ->assertSee(__('Nothing to do'));
});
