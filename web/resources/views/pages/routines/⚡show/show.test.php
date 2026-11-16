<?php

use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

test('renders successfully', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    actingAs($user)
        ->get(route('routines.show', $routine))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::routines.show', ['routine' => $routine])
        ->assertOk();
})->group('smoke');

describe('authorization', function () {
    test('another team cannot open it', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->create();

        actingAs($user)
            ->get(route('routines.show', $routine))
            ->assertForbidden();
    });

    test('another team cannot delete it', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->call('delete')
            ->assertForbidden();

        expect($routine->fresh())->not->toBeTrashed();
    });
});

describe('validation', function () {
    test('a step name longer than the column is rejected', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set('newStep', str_repeat('a', 256))
            ->call('addStep')
            ->assertHasErrors(['newStep' => 'max']);

        expect($routine->steps()->count())->toBe(0);
    });

    test('renaming a step to something too long is rejected', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $step = RoutineStep::factory()->for($routine)->create(['name' => 'Weigh in']);

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set("names.{$step->id}", str_repeat('a', 256))
            ->call('rename', $step->id)
            ->assertHasErrors("names.{$step->id}");

        expect($step->fresh()->name)->toBe('Weigh in');
    });

    test('ignores a blank step without erroring', function (string $name) {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set('newStep', $name)
            ->call('addStep')
            ->assertHasNoErrors();

        expect($routine->steps()->count())->toBe(0);
    })->with(['', '  ']);

    test('a step name at the limit is accepted', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set('newStep', str_repeat('a', 255))
            ->call('addStep')
            ->assertHasNoErrors();

        expect($routine->steps()->count())->toBe(1);
    });

    test('renaming a step to the limit is accepted', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $step = RoutineStep::factory()->for($routine)->create(['name' => 'Weigh in']);

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set("names.{$step->id}", str_repeat('a', 255))
            ->call('rename', $step->id)
            ->assertHasNoErrors();

        expect($step->fresh()->name)->toBe(str_repeat('a', 255));
    });

    test('surrounding whitespace is trimmed off a new step', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set('newStep', '  Brush teeth  ')
            ->call('addStep');

        expect($routine->steps()->sole()->name)->toBe('Brush teeth');
    });

    test('surrounding whitespace is trimmed off a rename', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $step = RoutineStep::factory()->for($routine)->create(['name' => 'Wiegh in']);

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set("names.{$step->id}", '  Weigh in  ')
            ->call('rename', $step->id);

        expect($step->fresh()->name)->toBe('Weigh in');
    });

    test('renaming a step to blank is ignored without erroring', function (string $name) {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $step = RoutineStep::factory()->for($routine)->create(['name' => 'Weigh in']);

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set("names.{$step->id}", $name)
            ->call('rename', $step->id)
            ->assertHasNoErrors()
            ->assertSet("names.{$step->id}", 'Weigh in');

        expect($step->fresh()->name)->toBe('Weigh in');
    })->with(['', '  ']);
});

describe('crud and actions', function () {
    test('adds steps in order', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set('newStep', 'Brush teeth')
            ->call('addStep')
            ->assertSet('newStep', '')
            ->set('newStep', 'Make bed')
            ->call('addStep');

        expect($routine->steps()->pluck('name')->all())->toBe(['Brush teeth', 'Make bed']);
    });

    test('renames a step', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $step = RoutineStep::factory()->for($routine)->create(['name' => 'Wiegh in']);

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set("names.{$step->id}", 'Weigh in')
            ->call('rename', $step->id);

        expect($step->fresh()->name)->toBe('Weigh in');
    });

    test('renaming to blank restores the old name', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $step = RoutineStep::factory()->for($routine)->create(['name' => 'Weigh in']);

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->set("names.{$step->id}", '')
            ->call('rename', $step->id)
            ->assertSet("names.{$step->id}", 'Weigh in');

        expect($step->fresh()->name)->toBe('Weigh in');
    });

    test('soft deletes a removed step', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $step = RoutineStep::factory()->for($routine)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->call('removeStep', $step->id);

        expect($step->fresh())->toBeTrashed()
            ->and($routine->steps()->count())->toBe(0);
    });

    test('reorders steps', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $first = RoutineStep::factory()->for($routine)->create(['name' => 'First']);
        $second = RoutineStep::factory()->for($routine)->create(['name' => 'Second']);

        $component = Livewire::actingAs($user)->test('pages::routines.show', ['routine' => $routine]);

        $component->call('moveDown', $first->id);
        expect($routine->steps()->pluck('name')->all())->toBe(['Second', 'First']);

        $component->call('moveUp', $first->id);
        expect($routine->steps()->pluck('name')->all())->toBe(['First', 'Second']);
    });

    test('moving past either end does nothing', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $first = RoutineStep::factory()->for($routine)->create(['name' => 'First']);
        $second = RoutineStep::factory()->for($routine)->create(['name' => 'Second']);

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->call('moveUp', $first->id)
            ->call('moveDown', $second->id);

        expect($routine->steps()->pluck('name')->all())->toBe(['First', 'Second']);
    });

    test('edits the routine from the header', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create(['name' => 'Bedtime']);

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->call('edit')
            ->assertSet('form.name', 'Bedtime')
            ->set('form.name', 'Wind down')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Wind down');

        expect($routine->fresh()->name)->toBe('Wind down');
    });

    test('pauses and resumes the routine', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create(['is_active' => true]);

        $component = Livewire::actingAs($user)->test('pages::routines.show', ['routine' => $routine]);

        $component->call('toggleActive')->assertSee('Resume');
        expect($routine->fresh())->is_active->toBeFalse();

        $component->call('toggleActive')->assertDontSee('Resume');
        expect($routine->fresh())->is_active->toBeTrue();
    });

    test('deletes the routine and returns to the list', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->call('delete')
            ->assertRedirect(route('routines.index'));

        expect($routine->fresh())->toBeTrashed();
    });
});

describe('details', function () {
    test('shows the schedule and who it is assigned to', function () {
        $user = User::factory()->create(['name' => 'Nami']);
        $routine = Routine::factory()->for($user->currentTeam)->for($user)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->assertSeeInOrder([
                'Time of day', $routine->time_of_day->getLabel(),
                'Repeats', $routine->scheduleSummary(),
                'Assigned to', 'Nami',
            ]);
    });

    test('last done is not yet when no day was finished', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $unfinished = RoutineOccurrence::factory()->for($routine)->create();
        RoutineOccurrenceStep::factory()->for($unfinished, 'occurrence')->completed()->create();
        RoutineOccurrenceStep::factory()->for($unfinished, 'occurrence')->create();
        RoutineOccurrence::factory()->for($routine)->dueOn(now()->subDay())->create();

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->assertSeeInOrder(['Last done', 'Not yet']);
    });

    test('last done is the latest day with every step finished', function (int $daysAgo, string $expected) {
        travelTo(now()->setDate(2026, 11, 15)->setTime(12, 0));

        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();
        $today = $user->currentTeam->today();

        $finished = RoutineOccurrence::factory()->for($routine)->dueOn($today->subDays($daysAgo))->create();
        RoutineOccurrenceStep::factory()->for($finished, 'occurrence')->completed()->count(2)->create();

        $older = RoutineOccurrence::factory()->for($routine)->dueOn($today->subDays($daysAgo + 1))->create();
        RoutineOccurrenceStep::factory()->for($older, 'occurrence')->completed()->create();

        if ($daysAgo > 0) {
            $unfinished = RoutineOccurrence::factory()->for($routine)->dueOn($today)->create();
            RoutineOccurrenceStep::factory()->for($unfinished, 'occurrence')->create();
        }

        Livewire::actingAs($user)
            ->test('pages::routines.show', ['routine' => $routine])
            ->assertSeeInOrder(['Last done', $expected]);
    })->with([
        'today' => [0, 'Today'],
        'yesterday' => [1, 'Yesterday'],
        'earlier this year' => [5, 'Tuesday, November 10'],
        'a past year' => [400, 'October 11, 2025'],
    ]);
});

test('breadcrumb links back to the index', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::routines.show', ['routine' => $routine])
        ->assertSeeHtml('href="' . route('routines.index') . '"');
});
