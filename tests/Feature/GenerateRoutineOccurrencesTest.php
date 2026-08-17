<?php

use App\Actions\Routines\GenerateRoutineOccurrences;
use App\Enums\TimeOfDay;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\Team;
use Carbon\Carbon;

beforeEach(function () {
    $this->generate = app(GenerateRoutineOccurrences::class);
});

test('it generates an occurrence with a row per step', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->count(3)->create();

    $created = $this->generate->handle($team, Carbon::parse('2026-08-10'));

    expect($created)->toBe(1)
        ->and(RoutineOccurrence::count())->toBe(1)
        ->and(RoutineOccurrenceStep::count())->toBe(3);
});

test('it skips routines that are not due', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->weekly([Carbon::MONDAY])->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->create();

    $created = $this->generate->handle($team, Carbon::parse('2026-08-11')); // Tuesday

    expect($created)->toBe(0)
        ->and(RoutineOccurrence::count())->toBe(0);
});

test('it skips paused routines', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->inactive()->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->create();

    expect($this->generate->handle($team, Carbon::parse('2026-08-10')))->toBe(0);
});

test('running it twice creates nothing the second time', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->count(2)->create();

    $this->generate->handle($team, Carbon::parse('2026-08-10'));
    $second = $this->generate->handle($team, Carbon::parse('2026-08-10'));

    expect($second)->toBe(0)
        ->and(RoutineOccurrence::count())->toBe(1)
        ->and(RoutineOccurrenceStep::count())->toBe(2);
});

test('it preserves completions when run again', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->create();

    $this->generate->handle($team, Carbon::parse('2026-08-10'));
    RoutineOccurrenceStep::first()->complete();

    $this->generate->handle($team, Carbon::parse('2026-08-10'));

    expect(RoutineOccurrenceStep::first()->isCompleted())->toBeTrue();
});

test('a step added later backfills into an existing occurrence', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->create();

    $this->generate->handle($team, Carbon::parse('2026-08-10'));
    expect(RoutineOccurrenceStep::count())->toBe(1);

    RoutineStep::factory()->for($routine)->create();
    $this->generate->handle($team, Carbon::parse('2026-08-10'));

    expect(RoutineOccurrenceStep::count())->toBe(2);
});

test('a deleted step stops generating but keeps its history', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    $keep = RoutineStep::factory()->for($routine)->create();
    $remove = RoutineStep::factory()->for($routine)->create();

    $this->generate->handle($team, Carbon::parse('2026-08-10'));
    $remove->delete();
    $this->generate->handle($team, Carbon::parse('2026-08-11'));

    $yesterday = RoutineOccurrence::whereDate('due_on', '2026-08-10')->sole();
    $today = RoutineOccurrence::whereDate('due_on', '2026-08-11')->sole();

    expect($yesterday->steps)->toHaveCount(2)
        ->and($today->steps)->toHaveCount(1)
        ->and($today->steps->first()->routine_step_id)->toBe($keep->id);
});

test('a removed step still resolves its name on a past day', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    $step = RoutineStep::factory()->for($routine)->create(['name' => 'Weigh in']);

    $this->generate->handle($team, Carbon::parse('2026-08-10'));
    $step->delete();

    $occurrenceStep = RoutineOccurrenceStep::first();

    expect($occurrenceStep->step->name)->toBe('Weigh in');
});

test('it generates across a date range', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->create();

    $created = $this->generate->handle($team, Carbon::parse('2026-08-10'), Carbon::parse('2026-08-16'));

    expect($created)->toBe(7)
        ->and(RoutineOccurrence::count())->toBe(7);
});

test('an inverted range generates nothing', function () {
    $team = Team::factory()->create();
    Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);

    expect($this->generate->handle($team, Carbon::parse('2026-08-16'), Carbon::parse('2026-08-10')))->toBe(0);
});

test('it only touches the given team', function () {
    $team = Team::factory()->create();
    $other = Team::factory()->create();
    Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    Routine::factory()->for($other)->daily()->create(['starts_on' => '2026-08-01']);

    $this->generate->handle($team, Carbon::parse('2026-08-10'));

    expect(RoutineOccurrence::count())->toBe(1);
});

test('a routine with no steps generates an occurrence with no step rows', function () {
    $team = Team::factory()->create();
    Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);

    $this->generate->handle($team, Carbon::parse('2026-08-10'));

    expect(RoutineOccurrence::count())->toBe(1)
        ->and(RoutineOccurrenceStep::count())->toBe(0);
});

test('the due date follows the team timezone', function () {
    $team = Team::factory()->create(['timezone' => 'Pacific/Kiritimati']); // UTC+14
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->create();

    // 23:00 UTC on the 10th is already the 11th where this team lives.
    $this->travelTo(Carbon::parse('2026-08-10 23:00', 'UTC'));
    $this->generate->handle($team);

    expect(RoutineOccurrence::sole()->due_on->toDateString())->toBe('2026-08-11');
});

test('forDate generates and returns the day sorted by time of day', function () {
    $team = Team::factory()->create();

    $routines = [
        [TimeOfDay::Evening, 'Wind down'],
        [TimeOfDay::Morning, 'Wake up'],
        [TimeOfDay::Anytime, 'Water plants'],
        [TimeOfDay::Morning, 'Feed the cat'],
    ];

    foreach ($routines as [$timeOfDay, $name]) {
        $routine = Routine::factory()->for($team)->daily()->timeOfDay($timeOfDay)
            ->create(['starts_on' => '2026-08-01', 'name' => $name]);

        RoutineStep::factory()->for($routine)->create();
    }

    $occurrences = $this->generate->forDate($team, Carbon::parse('2026-08-10'));

    expect($occurrences->pluck('routine.name')->all())
        ->toBe(['Feed the cat', 'Wake up', 'Wind down', 'Water plants']);
});

test('forDate still shows a routine that has since been paused', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => '2026-08-01']);
    RoutineStep::factory()->for($routine)->create();

    $this->generate->handle($team, Carbon::parse('2026-08-10'));
    $routine->update(['is_active' => false]);

    expect($this->generate->forDate($team, Carbon::parse('2026-08-10')))->toHaveCount(1);
});

test('warm generates the next week for every team', function () {
    $first = Team::factory()->create();
    $second = Team::factory()->create();

    foreach ([$first, $second] as $team) {
        $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => now()->subMonth()]);
        RoutineStep::factory()->for($routine)->create();
    }

    $created = $this->generate->warm(7);

    expect($created)->toBe(14)
        ->and(RoutineOccurrence::count())->toBe(14);
});

test('the command warms the requested number of days', function () {
    $team = Team::factory()->create();
    $routine = Routine::factory()->for($team)->daily()->create(['starts_on' => now()->subMonth()]);
    RoutineStep::factory()->for($routine)->create();

    $this->artisan('routines:generate', ['--days' => 3])->assertSuccessful();

    expect(RoutineOccurrence::count())->toBe(3);
});
