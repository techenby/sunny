<?php

use App\Enums\RoutineFrequency;
use App\Enums\TeamRole;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Str;

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

test('store creates a routine with its steps', function () {
    $user = User::factory()->create(['name' => 'Sam']);

    $response = $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Bedtime',
            'user_id' => $user->id,
            'time_of_day' => 'evening',
            'frequency' => 'weekly',
            'weekdays' => [1, 3],
            'starts_on' => '2026-10-01',
            'steps' => [['name' => 'Pajamas'], ['name' => 'Brush teeth']],
        ])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Bedtime')
        ->assertJsonPath('data.team_id', $user->current_team_id)
        ->assertJsonPath('data.user', ['id' => $user->id, 'name' => 'Sam'])
        ->assertJsonPath('data.weekdays', [1, 3])
        ->assertJsonPath('data.schedule_summary', 'Mon, Wed')
        ->assertJsonPath('data.steps.0.name', 'Pajamas')
        ->assertJsonPath('data.steps.1.name', 'Brush teeth');

    $routine = Routine::find($response->json('data.id'));

    expect($routine->steps->pluck('position')->all())->toBe([1, 2])
        ->and($routine->is_active)->toBeTrue();
});

test('store starts a routine today in the team timezone by default', function () {
    Date::setTestNow('2026-10-02 03:00:00');
    $user = User::factory()->create();
    $user->currentTeam->update(['timezone' => 'America/Chicago']);

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Feed the cat',
            'time_of_day' => 'morning',
            'frequency' => 'daily',
        ])
        ->assertCreated()
        ->assertJsonPath('data.starts_on', fn (string $value): bool => str_starts_with($value, '2026-10-01'))
        ->assertJsonPath('data.user', null)
        ->assertJsonCount(0, 'data.steps');
});

test('store only keeps the schedule fields the frequency uses', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Feed the cat',
            'time_of_day' => 'morning',
            'frequency' => 'daily',
            'weekdays' => [1],
            'day_of_month' => 4,
        ])
        ->assertCreated()
        ->assertJsonPath('data.weekdays', null)
        ->assertJsonPath('data.day_of_month', null);
});

test('store returns the existing routine when a client uuid is retried', function () {
    $user = User::factory()->create();
    $payload = ['name' => 'Feed the cat', 'time_of_day' => 'morning', 'frequency' => 'daily', 'client_uuid' => (string) Str::uuid()];

    $id = $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), $payload)
        ->assertCreated()
        ->json('data.id');

    $this->postJson(route('api.routines.store', $user->currentTeam), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $id);

    expect(Routine::count())->toBe(1);
});

test('store validates the routine', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'time_of_day' => 'midnight',
            'frequency' => 'monthly',
            'steps' => [['name' => '']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'time_of_day', 'day_of_month', 'steps.0.name']);

    $this->postJson(route('api.routines.store', $user->currentTeam), [
        'name' => 'Trash',
        'time_of_day' => 'evening',
        'frequency' => 'weekly',
        'weekdays' => [],
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('weekdays');
});

test('store rejects an assignee who is not on the team', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', $user->currentTeam), [
            'name' => 'Feed the cat',
            'time_of_day' => 'morning',
            'frequency' => 'daily',
            'user_id' => User::factory()->create()->id,
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('user_id');
});

test('store returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.store', Team::factory()->create()), [
            'name' => 'Feed the cat',
            'time_of_day' => 'morning',
            'frequency' => 'daily',
        ])
        ->assertForbidden();
});

test('update modifies a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->weekly()->create(['name' => 'Trash']);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), [
            'name' => 'Recycling',
            'frequency' => 'monthly',
            'day_of_month' => 15,
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Recycling')
        ->assertJsonPath('data.schedule_summary', 'Monthly on the 15th');

    $routine->refresh();

    expect($routine->frequency)->toBe(RoutineFrequency::Monthly)
        ->and($routine->weekdays)->toBeNull()
        ->and($routine->is_active)->toBeFalse();
});

test('update renames, reorders, adds and removes steps', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $pajamas = RoutineStep::factory()->for($routine)->create(['name' => 'Pajamas', 'position' => 1]);
    $teeth = RoutineStep::factory()->for($routine)->create(['name' => 'Brush teeth', 'position' => 2]);
    $story = RoutineStep::factory()->for($routine)->create(['name' => 'Story', 'position' => 3]);

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), [
            'steps' => [
                ['id' => $teeth->id, 'name' => 'Brush teeth'],
                ['name' => 'Bath'],
                ['id' => $pajamas->id, 'name' => 'Put on pajamas'],
            ],
        ])
        ->assertOk()
        ->assertJsonCount(3, 'data.steps')
        ->assertJsonPath('data.steps.0.id', $teeth->id)
        ->assertJsonPath('data.steps.1.name', 'Bath')
        ->assertJsonPath('data.steps.2.id', $pajamas->id)
        ->assertJsonPath('data.steps.2.name', 'Put on pajamas');

    expect($story->fresh()->trashed())->toBeTrue();
});

test('update leaves steps alone when they are not sent', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    RoutineStep::factory()->for($routine)->count(2)->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['name' => 'Bedtime'])
        ->assertOk()
        ->assertJsonCount(2, 'data.steps');
});

test('update rejects steps from another routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $other = RoutineStep::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), [
            'steps' => [['id' => $other->id, 'name' => 'Sneaky']],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('steps.0.id');
});

test('update returns 404 for a routine under a different team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.update', [$user->currentTeam, $routine]), ['name' => 'Mine now'])
        ->assertNotFound();
});

test('destroy deletes a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.routines.destroy', [$user->currentTeam, $routine]))
        ->assertNoContent();

    expect($routine->fresh()->trashed())->toBeTrue();
});

test('destroy returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.routines.destroy', [$routine->team, $routine]))
        ->assertForbidden();

    expect($routine->fresh()->trashed())->toBeFalse();
});

test('any team member can manage routines', function () {
    $owner = User::factory()->create();
    $member = User::factory()->create();
    $member->teams()->attach($owner->currentTeam, ['role' => TeamRole::Member]);
    $routine = Routine::factory()->for($owner->currentTeam)->create();

    $this->actingAs($member)
        ->patchJson(route('api.routines.update', [$owner->currentTeam, $routine]), ['user_id' => $member->id])
        ->assertOk()
        ->assertJsonPath('data.user.id', $member->id);
});
