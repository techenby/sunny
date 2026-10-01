<?php

use App\Enums\TeamRole;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineOccurrenceStep;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

test('guests cannot access routines', function () {
    $this->getJson(route('api.routines.index', 'household'))->assertUnauthorized();
    $this->getJson(route('api.routines.show', ['household', 1]))->assertUnauthorized();
    $this->postJson(route('api.routines.store', 'household'))->assertUnauthorized();
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

test('store creates a minimal daily routine', function () {
    $user = User::factory()->create();
    $this->travelTo(now()->setDate(2026, 9, 30));

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Morning',
            'time_of_day' => 'morning',
            'frequency' => 'daily',
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Morning')
        ->assertJsonPath('data.team_id', $user->currentTeam->id)
        ->assertJsonPath('data.frequency', 'daily')
        ->assertJsonPath('data.weekdays', null)
        ->assertJsonPath('data.day_of_month', null)
        ->assertJsonPath('data.is_active', true)
        ->assertJsonPath('data.user', null)
        ->assertJsonPath('data.deleted_at', null)
        ->assertJsonPath('data.steps', []);

    $routine = Routine::firstOrFail();
    expect($routine->starts_on->toDateString())->toBe($user->currentTeam->today()->toDateString());
});

test('store normalises weekdays and day of month to the frequency', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Weekly',
            'time_of_day' => 'evening',
            'frequency' => 'weekly',
            'weekdays' => ['3' => 3, '1' => 1],
            'day_of_month' => 5,
            'starts_on' => '2026-10-01',
            'is_active' => false,
        ])
        ->assertCreated()
        ->assertJsonPath('data.weekdays', [3, 1])
        ->assertJsonPath('data.day_of_month', null)
        ->assertJsonPath('data.starts_on', fn ($value) => str_starts_with($value, '2026-10-01'))
        ->assertJsonPath('data.is_active', false);
});

test('store requires weekdays for weekly and day of month for monthly', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'A', 'time_of_day' => 'morning', 'frequency' => 'weekly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weekdays']);

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'A', 'time_of_day' => 'morning', 'frequency' => 'monthly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['day_of_month']);

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'A', 'time_of_day' => 'morning', 'frequency' => 'monthly', 'day_of_month' => 31])
        ->assertCreated()
        ->assertJsonPath('data.day_of_month', 31)
        ->assertJsonPath('data.weekdays', null);
});

test('store validates required fields', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'time_of_day', 'frequency']);
});

test('store creates steps in order', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Bedtime',
            'time_of_day' => 'evening',
            'frequency' => 'daily',
            'steps' => [['name' => 'Pajamas'], ['name' => 'Teeth'], ['name' => 'Story']],
        ])
        ->assertCreated()
        ->assertJsonCount(3, 'data.steps')
        ->assertJsonPath('data.steps.0.name', 'Pajamas')
        ->assertJsonPath('data.steps.0.position', 1)
        ->assertJsonPath('data.steps.2.name', 'Story')
        ->assertJsonPath('data.steps.2.position', 3);
});

test('store rejects blank step names and step ids', function () {
    $user = User::factory()->create();
    $other = RoutineStep::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Bedtime',
            'time_of_day' => 'evening',
            'frequency' => 'daily',
            'steps' => [['name' => '   '], ['id' => $other->id, 'name' => 'Teeth']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps.0.name', 'steps.1.id']);

    expect(Routine::count())->toBe(1);
});

test('store supports household and assigned routines', function () {
    $user = User::factory()->create();
    $member = User::factory()->create();
    $user->currentTeam->members()->attach($member, ['role' => TeamRole::Member->value]);

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'Chores', 'time_of_day' => 'morning', 'frequency' => 'daily', 'user_id' => null])
        ->assertCreated()
        ->assertJsonPath('data.user', null);

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'Homework', 'time_of_day' => 'morning', 'frequency' => 'daily', 'user_id' => $member->id])
        ->assertCreated()
        ->assertJsonPath('data.user.id', $member->id);
});

test('store rejects an assignee who is not on the team', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), ['name' => 'Homework', 'time_of_day' => 'morning', 'frequency' => 'daily', 'user_id' => $stranger->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);
});

test('store returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $team), ['name' => 'A', 'time_of_day' => 'morning', 'frequency' => 'daily'])
        ->assertForbidden();
});

test('store is idempotent on client_uuid', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $payload = ['name' => 'Morning', 'time_of_day' => 'morning', 'frequency' => 'daily', 'client_uuid' => $uuid];

    $first = $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), $payload)
        ->assertCreated()
        ->assertJsonPath('data.client_uuid', $uuid);

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    Routine::find($first->json('data.id'))->delete();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Routine::withTrashed()->where('client_uuid', $uuid)->count())->toBe(1);
});

test('update renames a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create(['name' => 'Old']);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.frequency', 'daily');

    expect($routine->refresh()->name)->toBe('New');
});

test('update accepts a partial payload on a weekly routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->weekly([Carbon::MONDAY])->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['weekdays' => [1, 3]])
        ->assertOk()
        ->assertJsonPath('data.weekdays', [1, 3])
        ->assertJsonPath('data.frequency', 'weekly');

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['is_active' => false])
        ->assertOk()
        ->assertJsonPath('data.weekdays', [1, 3])
        ->assertJsonPath('data.is_active', false);
});

test('update requires weekdays when switching to weekly', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['frequency' => 'weekly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['weekdays']);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['frequency' => 'monthly'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['day_of_month']);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['frequency' => 'weekly', 'weekdays' => [2]])
        ->assertOk()
        ->assertJsonPath('data.weekdays', [2]);
});

test('update clears stale weekdays when switching weekly to daily', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->weekly([1, 2])->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['frequency' => 'daily'])
        ->assertOk()
        ->assertJsonPath('data.weekdays', null)
        ->assertJsonPath('data.day_of_month', null);

    expect($routine->refresh()->weekdays)->toBeNull();
});

test('update can reassign to the household and rejects non-members', function () {
    $user = User::factory()->create();
    $stranger = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->assignedTo($user)->daily()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['user_id' => $stranger->id])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['user_id']);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['user_id' => null])
        ->assertOk()
        ->assertJsonPath('data.user', null);
});

test('update reorders renames adds and removes steps', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();
    [$a, $b, $c] = collect(['A', 'B', 'C'])
        ->map(fn ($name, $i) => RoutineStep::factory()->for($routine)->create(['name' => $name, 'position' => $i + 1]));

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), [
            'steps' => [
                ['id' => $c->id, 'name' => 'C renamed'],
                ['name' => 'New'],
                ['id' => $a->id, 'name' => 'A'],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(3, 'data.steps')
        ->assertJsonPath('data.steps.0.id', $c->id)
        ->assertJsonPath('data.steps.0.name', 'C renamed')
        ->assertJsonPath('data.steps.0.position', 1)
        ->assertJsonPath('data.steps.1.name', 'New')
        ->assertJsonPath('data.steps.1.position', 2)
        ->assertJsonPath('data.steps.2.id', $a->id)
        ->assertJsonPath('data.steps.2.position', 3);

    $this->assertSoftDeleted('routine_steps', ['id' => $b->id]);
});

test('removing a step keeps past occurrence steps', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();
    $step = RoutineStep::factory()->for($routine)->create();
    $occurrence = RoutineOccurrence::factory()->for($routine)->create();
    $occurrenceStep = RoutineOccurrenceStep::factory()->for($occurrence, 'occurrence')->for($step, 'step')->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['steps' => []])
        ->assertOk()
        ->assertJsonCount(0, 'data.steps');

    $this->assertSoftDeleted('routine_steps', ['id' => $step->id]);
    $this->assertDatabaseHas('routine_occurrence_steps', ['id' => $occurrenceStep->id]);
});

test('update leaves steps untouched when steps is absent', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();
    RoutineStep::factory()->for($routine)->count(2)->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['name' => 'Renamed'])
        ->assertOk()
        ->assertJsonCount(2, 'data.steps');
});

test('update rejects step ids from another routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();
    $foreign = RoutineStep::factory()->for(Routine::factory()->for($user->currentTeam)->daily())->create();
    $trashed = RoutineStep::factory()->for($routine)->create();
    $trashed->delete();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['steps' => [['id' => $foreign->id, 'name' => 'Stolen']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps.0.id']);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['steps' => [['id' => $trashed->id, 'name' => 'Zombie']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps.0.id']);

    expect($foreign->refresh()->name)->not->toBe('Stolen');
});

test('update rejects blank step names', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['steps' => [['name' => '  ']]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['steps.0.name']);
});

test('update returns 404 for a routine under a different team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['name' => 'Nope'])
        ->assertNotFound();
});

test('update returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$routine->team, $routine]), ['name' => 'Nope'])
        ->assertForbidden();
});

test('destroy soft deletes a routine', function () {
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

test('destroy returns 404 for a routine under a different team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.routines.destroy', [$user->currentTeam, $routine]))
        ->assertNotFound();
});

test('changing a step touches its routine', function () {
    $routine = Routine::factory()->create(['updated_at' => now()->subWeek()]);
    $step = RoutineStep::factory()->for($routine)->create();

    DB::table('routines')->where('id', $routine->id)->update(['updated_at' => now()->subWeek()]);
    $step->update(['name' => 'Changed']);
    expect($routine->refresh()->updated_at->isToday())->toBeTrue();

    DB::table('routines')->where('id', $routine->id)->update(['updated_at' => now()->subWeek()]);
    $step->delete();
    expect($routine->refresh()->updated_at->isToday())->toBeTrue();

    DB::table('routines')->where('id', $routine->id)->update(['updated_at' => now()->subWeek()]);
    RoutineStep::factory()->for($routine)->create();
    expect($routine->refresh()->updated_at->isToday())->toBeTrue();
});
