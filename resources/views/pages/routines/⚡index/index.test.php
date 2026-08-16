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
            ->user_id->toBeNull()
            ->is_active->toBeTrue();
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
