<?php

use Illuminate\Support\Facades\Date;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\UniqueConstraintViolationException;

test('a routine belongs to a team', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->create();

    expect($routine->team->id)->toBe($team->id)
        ->and($team->routines)->toHaveOne();
});

test('a routine can be assigned to a team member', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->assignedTo($user)->create();

    expect($routine->user->id)->toBe($user->id);
});

test('the household scope finds unassigned routines', function () {
    Routine::factory()->assignedTo(User::factory()->create())->create();
    Routine::factory()->household()->count(2)->create();

    expect(Routine::household()->count())->toBe(2);
});

test('steps are ordered by position and auto-assigned to the end', function () {
    $routine = Routine::factory()->create();

    $first = RoutineStep::factory()->for($routine)->create(['name' => 'Brush teeth']);
    $second = RoutineStep::factory()->for($routine)->create(['name' => 'Make bed']);

    expect($first->position)->toBe(1)
        ->and($second->position)->toBe(2)
        ->and($routine->steps->pluck('name')->all())->toBe(['Brush teeth', 'Make bed']);
});

test('a deleted step is soft deleted so history stays intact', function () {
    $step = RoutineStep::factory()->create();

    $step->delete();

    expect($step)->toBeTrashed()
        ->and(RoutineStep::count())->toBe(0)
        ->and(RoutineStep::withTrashed()->count())->toBe(1);
});

test('a daily routine occurs every day', function () {
    $routine = Routine::factory()->daily()->create(['starts_on' => '2026-08-01']);

    expect($routine->occursOn(Date::parse('2026-08-10')))->toBeTrue()
        ->and($routine->occursOn(Date::parse('2026-08-11')))->toBeTrue();
});

test('a weekly routine occurs only on its chosen weekdays', function () {
    $routine = Routine::factory()
        ->weekly([Carbon::MONDAY, Carbon::THURSDAY])
        ->create(['starts_on' => '2026-08-01']);

    expect($routine->occursOn(Date::parse('2026-08-10')))->toBeTrue()  // Monday
        ->and($routine->occursOn(Date::parse('2026-08-13')))->toBeTrue()  // Thursday
        ->and($routine->occursOn(Date::parse('2026-08-11')))->toBeFalse(); // Tuesday
});

test('weekdays stored as strings still match', function () {
    $routine = Routine::factory()->create([
        'frequency' => RoutineFrequency::Weekly,
        'weekdays' => ['1', '4'],
        'starts_on' => '2026-08-01',
    ]);

    expect($routine->occursOn(Date::parse('2026-08-10')))->toBeTrue()
        ->and($routine->occursOn(Date::parse('2026-08-11')))->toBeFalse();
});

test('a monthly routine occurs on its chosen day', function () {
    $routine = Routine::factory()->monthly(15)->create(['starts_on' => '2026-01-01']);

    expect($routine->occursOn(Date::parse('2026-08-15')))->toBeTrue()
        ->and($routine->occursOn(Date::parse('2026-09-15')))->toBeTrue()
        ->and($routine->occursOn(Date::parse('2026-08-16')))->toBeFalse();
});

test('a monthly routine set past the end of a short month runs on its last day', function () {
    $routine = Routine::factory()->monthly(31)->create(['starts_on' => '2026-01-01']);

    expect($routine->occursOn(Date::parse('2026-02-28')))->toBeTrue()
        ->and($routine->occursOn(Date::parse('2026-02-27')))->toBeFalse()
        ->and($routine->occursOn(Date::parse('2026-03-31')))->toBeTrue()
        ->and($routine->occursOn(Date::parse('2026-03-30')))->toBeFalse();
});

test('a routine never occurs before it starts', function () {
    $routine = Routine::factory()->daily()->create(['starts_on' => '2026-08-10']);

    expect($routine->occursOn(Date::parse('2026-08-09')))->toBeFalse()
        ->and($routine->occursOn(Date::parse('2026-08-10')))->toBeTrue();
});

test('an inactive routine never occurs', function () {
    $routine = Routine::factory()->daily()->inactive()->create(['starts_on' => '2026-08-01']);

    expect($routine->occursOn(Date::parse('2026-08-10')))->toBeFalse();
});

test('the active scope excludes paused routines', function () {
    Routine::factory()->count(2)->create();
    Routine::factory()->inactive()->create();

    expect(Routine::active()->count())->toBe(2);
});

test('the time of day scope filters routines', function () {
    Routine::factory()->timeOfDay(TimeOfDay::Morning)->count(2)->create();
    Routine::factory()->timeOfDay(TimeOfDay::Evening)->create();

    expect(Routine::forTimeOfDay(TimeOfDay::Morning)->count())->toBe(2);
});

test('an occurrence tracks progress across its steps', function () {
    $occurrence = RoutineOccurrence::factory()->create();

    RoutineOccurrenceStep::factory()->for($occurrence, 'occurrence')->count(3)->create();
    RoutineOccurrenceStep::factory()->for($occurrence, 'occurrence')->completed()->create();

    expect($occurrence->progress())->toBe(25)
        ->and($occurrence->isComplete())->toBeFalse();
});

test('an occurrence is complete once every step is checked', function () {
    $occurrence = RoutineOccurrence::factory()->create();
    $step = RoutineOccurrenceStep::factory()->for($occurrence, 'occurrence')->create();

    expect($occurrence->isComplete())->toBeFalse();

    $step->complete();

    expect($occurrence->isComplete())->toBeTrue();
});

test('completing an occurrence step records who did it', function () {
    $user = User::factory()->create();
    $step = RoutineOccurrenceStep::factory()->create();

    $step->complete($user);

    expect($step->isCompleted())->toBeTrue()
        ->and($step->completedBy->id)->toBe($user->id);
});

test('toggling an occurrence step flips its state', function () {
    $step = RoutineOccurrenceStep::factory()->create();

    $step->toggle();
    expect($step->isCompleted())->toBeTrue();

    $step->toggle();
    expect($step->isCompleted())->toBeFalse()
        ->and($step->completed_by)->toBeNull();
});

test('checking off one day does not affect another', function () {
    $routine = Routine::factory()->daily()->create();
    $step = RoutineStep::factory()->for($routine)->create();

    $today = RoutineOccurrence::factory()->for($routine)->dueOn(now())->create();
    $yesterday = RoutineOccurrence::factory()->for($routine)->dueOn(now()->subDay())->create();

    RoutineOccurrenceStep::factory()
        ->for($today, 'occurrence')
        ->for($step, 'step')
        ->create()
        ->complete();

    $missed = RoutineOccurrenceStep::factory()
        ->for($yesterday, 'occurrence')
        ->for($step, 'step')
        ->create();

    expect($today->isComplete())->toBeTrue()
        ->and($yesterday->isComplete())->toBeFalse()
        ->and($missed->isCompleted())->toBeFalse();
});

test('a routine can only have one occurrence per date', function () {
    $routine = Routine::factory()->create();
    RoutineOccurrence::factory()->for($routine)->dueOn(now())->create();

    RoutineOccurrence::factory()->for($routine)->dueOn(now())->create();
})->throws(UniqueConstraintViolationException::class);

test('deleting a routine cascades to its occurrences and steps', function () {
    $routine = Routine::factory()->create();
    $step = RoutineStep::factory()->for($routine)->create();
    $occurrence = RoutineOccurrence::factory()->for($routine)->create();
    RoutineOccurrenceStep::factory()
        ->for($occurrence, 'occurrence')
        ->for($step, 'step')
        ->create();

    $routine->forceDelete();

    expect(RoutineStep::withTrashed()->count())->toBe(0)
        ->and(RoutineOccurrence::count())->toBe(0)
        ->and(RoutineOccurrenceStep::count())->toBe(0);
});

test('a routine is soft deleted', function () {
    $routine = Routine::factory()->create();

    $routine->delete();

    expect($routine)->toBeTrashed()
        ->and(Routine::count())->toBe(0);
});
