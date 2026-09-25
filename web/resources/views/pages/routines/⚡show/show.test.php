<?php

use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

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
});
