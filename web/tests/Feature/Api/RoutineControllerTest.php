<?php

use App\Enums\RoutineFrequency;
use App\Enums\TeamRole;
use App\Enums\TimeOfDay;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;

test('guests cannot access routines', function () {
    $this->getJson(route('api.routines.index', 'household'))->assertUnauthorized();
    $this->postJson(route('api.routines.store', 'household'))->assertUnauthorized();
    $this->getJson(route('api.routines.show', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.routines.update', ['household', 1]))->assertUnauthorized();
    $this->deleteJson(route('api.routines.destroy', ['household', 1]))->assertUnauthorized();
});

test('index returns routines for the team', function () {
    $user = User::factory()->create();
    Routine::factory()->for($user->currentTeam)->count(2)->create();
    Routine::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.routines.index', $user->currentTeam))
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonStructure(['data' => [['id', 'name', 'time_of_day', 'frequency', 'schedule_summary', 'user', 'steps']]]);
});

test('index returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.routines.index', $routine->team))
        ->assertForbidden();
});

test('show returns a routine with its steps in order and its assignee', function () {
    $user = User::factory()->create(['name' => 'Sam']);
    $routine = Routine::factory()->for($user->currentTeam)->assignedTo($user)->daily()->create(['name' => 'Bedtime']);
    RoutineStep::factory()->for($routine)->create(['name' => 'Brush teeth', 'position' => 2]);
    RoutineStep::factory()->for($routine)->create(['name' => 'Pajamas', 'position' => 1]);

    $this->actingAs($user)
        ->getJson(route('api.routines.show', [$user->currentTeam, $routine]))
        ->assertOk()
        ->assertJsonPath('data.name', 'Bedtime')
        ->assertJsonPath('data.schedule_summary', 'Every day')
        ->assertJsonPath('data.user', ['id' => $user->id, 'name' => 'Sam'])
        ->assertJsonPath('data.steps.0.name', 'Pajamas')
        ->assertJsonPath('data.steps.1.name', 'Brush teeth');
});

test('show returns a null user for a household routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->household()->create();

    $this->actingAs($user)
        ->getJson(route('api.routines.show', [$user->currentTeam, $routine]))
        ->assertOk()
        ->assertJsonPath('data.user', null);
});

test('show returns 404 for a routine under a different team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.routines.show', [$user->currentTeam, $routine]))
        ->assertNotFound();
});

test('store creates a routine and returns it', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Bedtime',
            'time_of_day' => 'evening',
            'frequency' => 'weekly',
            'weekdays' => [1, 3],
            'user_id' => $user->id,
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bedtime')
        ->assertJsonPath('data.time_of_day', 'evening')
        ->assertJsonPath('data.weekdays', [1, 3])
        ->assertJsonPath('data.schedule_summary', 'Mon, Wed')
        ->assertJsonPath('data.user.id', $user->id)
        ->assertJsonPath('data.steps', []);

    expect($user->currentTeam->routines()->sole())
        ->name->toBe('Bedtime')
        ->time_of_day->toBe(TimeOfDay::Evening)
        ->is_active->toBeTrue()
        ->starts_on->toDateString()->toBe($user->currentTeam->today()->toDateString());
});

test('store returns the existing routine when a client uuid is retried', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $payload = ['name' => 'Bedtime', 'time_of_day' => 'evening', 'frequency' => 'daily', 'client_uuid' => $uuid];

    $first = $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), $payload)
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Routine::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store returns the existing routine when a retry wins the race to insert it', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $raced = createAfterLookup('routines', 'client_uuid', fn () => Routine::factory()->for($user->currentTeam)->create(['name' => 'Bedtime', 'client_uuid' => $uuid]));

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'Bedtime', 'time_of_day' => 'evening', 'frequency' => 'daily', 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $raced->model->id);

    expect(Routine::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store drops schedule fields the frequency does not use', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Bedtime',
            'time_of_day' => 'evening',
            'frequency' => 'daily',
            'weekdays' => [1, 3],
            'day_of_month' => 4,
        ])
        ->assertCreated()
        ->assertJsonPath('data.weekdays', null)
        ->assertJsonPath('data.day_of_month', null);
});

test('store validates the routine', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['time_of_day' => 'midnight', 'frequency' => 'hourly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'time_of_day', 'frequency']);
});

test('store requires weekdays for a weekly routine and a day for a monthly one', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'Laundry', 'time_of_day' => 'anytime', 'frequency' => 'weekly', 'weekdays' => []])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('weekdays');

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'Bills', 'time_of_day' => 'anytime', 'frequency' => 'monthly', 'day_of_month' => 32])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('day_of_month');
});

test('store rejects an assignee who is not on the team', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'Bedtime', 'time_of_day' => 'evening', 'frequency' => 'daily', 'user_id' => $stranger->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');
});

test('store creates the routine in the team from the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $team), ['name' => 'Bedtime', 'time_of_day' => 'evening', 'frequency' => 'daily'])
        ->assertCreated();

    expect($team->routines()->count())->toBe(1)
        ->and($user->currentTeam->routines()->count())->toBe(0);
});

test('update modifies a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->weekly([1])->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), [
            'name' => 'Bills',
            'time_of_day' => 'afternoon',
            'frequency' => 'monthly',
            'day_of_month' => 15,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bills')
        ->assertJsonPath('data.weekdays', null)
        ->assertJsonPath('data.day_of_month', 15)
        ->assertJsonPath('data.is_active', false);

    expect($routine->fresh())
        ->frequency->toBe(RoutineFrequency::Monthly)
        ->time_of_day->toBe(TimeOfDay::Afternoon);
});

test('update leaves the schedule alone when the frequency is not sent', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->weekly([1, 5])->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['name' => 'Gym'])
        ->assertOk()
        ->assertJsonPath('data.weekdays', [1, 5]);
});

test('update can hand a routine to the whole household', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->assignedTo($user)->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['user_id' => null])
        ->assertOk()
        ->assertJsonPath('data.user', null);
});

test('update returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$routine->team, $routine]), ['name' => 'Nope'])
        ->assertForbidden();
});

test('update puts the steps in the order given', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create(['name' => 'Bedtime']);
    [$bath, $pajamas, $teeth, $story] = RoutineStep::factory()->for($routine)->count(4)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();
    $elsewhere = RoutineStep::factory()->create(['position' => 1]);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['step_order' => [$teeth->id, 999, $elsewhere->id, $bath->id]])
        ->assertOk()
        ->assertJsonPath('data.name', 'Bedtime')
        ->assertJsonPath('data.steps.0.id', $teeth->id)
        ->assertJsonPath('data.steps.1.id', $bath->id)
        ->assertJsonPath('data.steps.2.id', $pajamas->id)
        ->assertJsonPath('data.steps.3.id', $story->id);

    expect($routine->steps()->pluck('position')->all())->toBe([1, 2, 3, 4])
        ->and($elsewhere->fresh()->position)->toBe(1);
});

test('reordered steps are sent again in an incremental sync', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create(['updated_at' => now()->subWeek()]);
    [$first, $second] = RoutineStep::factory()->for($routine)->count(2)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create(['updated_at' => now()->subWeek()]);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['step_order' => [$second->id, $first->id]])
        ->assertOk();

    $steps = $this->actingAs($user)
        ->getJson(route('api.sync', ['since' => now()->subDay()->toIso8601String()]))
        ->assertOk()
        ->json('routine_steps');

    expect(collect($steps)->pluck('position', 'id')->all())->toEqual([$first->id => 2, $second->id => 1]);
});

test('update validates the step order', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['step_order' => ['brush teeth']])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['step_order.0' => 'The step_order.0 field must be an integer.']);
});

test('update does not reorder steps on a routine from another team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();
    [$first, $second] = RoutineStep::factory()->for($routine)->count(2)->sequence(fn ($sequence) => ['position' => $sequence->index + 1])->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$routine->team, $routine]), ['step_order' => [$second->id, $first->id]])
        ->assertForbidden();

    expect($routine->steps()->pluck('id')->all())->toBe([$first->id, $second->id]);
});

test('destroy deletes a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.routines.destroy', [$user->currentTeam, $routine]))
        ->assertNoContent();

    $this->assertSoftDeleted('routines', ['id' => $routine->id]);
});

test('destroy returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.routines.destroy', [$routine->team, $routine]))
        ->assertForbidden();
});
