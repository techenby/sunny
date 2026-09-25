<?php

use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use Carbon\Carbon;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $user = User::factory()->create();
    Routine::factory()->for($user->currentTeam)->create(['name' => 'Morning']);
    Routine::factory()->create(['name' => 'Someone else']);

    actingAs($user)
        ->get(route('routines.index'))
        ->assertOk()
        ->assertSee('Morning')
        ->assertDontSee('Someone else');

    Livewire::actingAs($user)
        ->test('pages::routines.index')
        ->assertOk()
        ->assertSee('Morning')
        ->assertDontSee('Someone else');
})->group('smoke');

describe('form validation', function () {
    test('requires weekdays for a weekly routine', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Sheets')
            ->set('form.frequency', RoutineFrequency::Weekly->value)
            ->call('save')
            ->assertHasErrors(['form.weekdays' => 'required']);
    });

    test('requires a day of month for a monthly routine', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Filters')
            ->set('form.frequency', RoutineFrequency::Monthly->value)
            ->call('save')
            ->assertHasErrors(['form.day_of_month' => 'required']);
    });

    test('requires a name', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', '')
            ->call('save')
            ->assertHasErrors(['form.name' => 'required']);

        expect(Routine::count())->toBe(0);
    });

    test('rejects a name longer than the column', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', str_repeat('a', 256))
            ->call('save')
            ->assertHasErrors(['form.name' => 'max']);

        expect(Routine::count())->toBe(0);
    });

    test('requires a start date', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Morning')
            ->set('form.starts_on', 'not-a-date')
            ->call('save')
            ->assertHasErrors(['form.starts_on' => 'date']);

        expect(Routine::count())->toBe(0);
    });

    test('rejects a frequency or time of day outside the enum', function (string $field, string $value) {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Morning')
            ->set("form.{$field}", $value)
            ->call('save')
            ->assertHasErrors("form.{$field}");

        expect(Routine::count())->toBe(0);
    })->with([
        ['frequency', 'fortnightly'],
        ['time_of_day', 'midnight'],
    ]);

    test('rejects a weekday outside the week', function (int $weekday) {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Sheets')
            ->set('form.frequency', RoutineFrequency::Weekly->value)
            ->set('form.weekdays', [$weekday])
            ->call('save')
            ->assertHasErrors('form.weekdays.0');

        expect(Routine::count())->toBe(0);
    })->with([-1, 7, 9]);

    test('accepts every real weekday', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Sheets')
            ->set('form.frequency', RoutineFrequency::Weekly->value)
            ->set('form.weekdays', [Carbon::SUNDAY, Carbon::SATURDAY])
            ->call('save')
            ->assertHasNoErrors();

        expect(Routine::sole()->scheduledWeekdays())->toBe([0, 6]);
    });

    test('rejects a day of month outside the month', function (int $day) {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Filters')
            ->set('form.frequency', RoutineFrequency::Monthly->value)
            ->set('form.day_of_month', $day)
            ->call('save')
            ->assertHasErrors(['form.day_of_month' => 'between']);

        expect(Routine::count())->toBe(0);
    })->with([0, 32]);

    test('accepts the first and last day of the month', function (int $day) {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Filters')
            ->set('form.frequency', RoutineFrequency::Monthly->value)
            ->set('form.day_of_month', $day)
            ->call('save')
            ->assertHasNoErrors();

        expect(Routine::sole()->day_of_month)->toBe($day);
    })->with([1, 31]);
});

describe('authorization', function () {
    test('will not assign a routine to someone outside the team', function () {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Dishes')
            ->set('form.user_id', $stranger->id)
            ->call('save')
            ->assertHasErrors('form.user_id');
    });

    test('will not edit another team routine', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('edit', $routine->id)
            ->assertForbidden();
    });
});

describe('crud and actions', function () {
    test('creates a routine and sends you to add steps', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Morning Routine')
            ->set('form.time_of_day', TimeOfDay::Morning->value)
            ->set('form.frequency', RoutineFrequency::Daily->value)
            ->call('save')
            ->assertRedirect();

        $routine = Routine::sole();

        expect($routine)
            ->name->toBe('Morning Routine')
            ->team_id->toBe($user->current_team_id)
            ->is_active->toBeTrue();
    });

    test('a one-person household defaults a new routine to that person', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->assertSet('form.user_id', $user->id);
    });

    test('a shared household defaults a new routine to nobody', function () {
        $user = User::factory()->create();
        User::factory()->memberOf($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->assertSet('form.user_id', null);
    });

    test('the default is only a suggestion and can be cleared', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('create')
            ->set('form.name', 'Chores')
            ->set('form.user_id', null)
            ->call('save');

        expect(Routine::sole()->user_id)->toBeNull();
    });

    test('editing an existing routine does not reassign it', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->household()->create();
        RoutineStep::factory()->for($routine)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('edit', $routine->id)
            ->assertSet('form.user_id', null)
            ->call('save');

        expect($routine->fresh()->user_id)->toBeNull();
    });

    test('edits an existing routine', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create(['name' => 'Old name']);
        RoutineStep::factory()->for($routine)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('edit', $routine->id)
            ->assertSet('form.name', 'Old name')
            ->set('form.name', 'New name')
            ->call('save')
            ->assertNoRedirect();

        expect($routine->fresh())->name->toBe('New name');
    });

    test('deletes a routine', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('delete', $routine->id);

        expect($routine->fresh())->toBeTrashed();
    });

    test('switching away from weekly clears the stale weekdays', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->weekly([Carbon::MONDAY])->create();

        Livewire::actingAs($user)
            ->test('pages::routines.index')
            ->call('edit', $routine->id)
            ->set('form.frequency', RoutineFrequency::Daily->value)
            ->call('save');

        expect($routine->fresh()->weekdays)->toBeNull();
    });

    test('pauses and resumes a routine', function () {
        $user = User::factory()->create();
        $routine = Routine::factory()->for($user->currentTeam)->create();

        $component = Livewire::actingAs($user)->test('pages::routines.index');

        $component->call('toggleActive', $routine->id);
        expect($routine->fresh())->is_active->toBeFalse();

        $component->call('toggleActive', $routine->id);
        expect($routine->fresh())->is_active->toBeTrue();
    });
});
