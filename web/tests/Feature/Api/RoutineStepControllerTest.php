<?php

use App\Enums\TeamRole;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\Team;
use App\Models\User;

test('guests cannot access routine steps', function () {
    $this->postJson(route('api.routines.steps.store', ['household', 1]))->assertUnauthorized();
    $this->patchJson(route('api.routines.steps.update', ['household', 1, 1]))->assertUnauthorized();
    $this->deleteJson(route('api.routines.steps.destroy', ['household', 1, 1]))->assertUnauthorized();
});

test('store adds a step to the end of the routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    RoutineStep::factory()->for($routine)->create(['position' => 3]);

    $this->actingAs($user)
        ->postJson(route('api.routines.steps.store', [$user->currentTeam, $routine]), ['name' => 'Brush teeth'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Brush teeth')
        ->assertJsonPath('data.routine_id', $routine->id)
        ->assertJsonPath('data.position', 4);
});

test('store returns the existing step when a client uuid is retried', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';

    $first = $this->actingAs($user)
        ->postJson(route('api.routines.steps.store', [$user->currentTeam, $routine]), ['name' => 'Brush teeth', 'client_uuid' => $uuid])
        ->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.routines.steps.store', [$user->currentTeam, $routine]), ['name' => 'Brush teeth', 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(RoutineStep::where('client_uuid', $uuid)->count())->toBe(1);
});

test('store validates the step name', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.steps.store', [$user->currentTeam, $routine]), ['name' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('store returns 404 for a deleted routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $routine->delete();

    $this->actingAs($user)
        ->postJson(route('api.routines.steps.store', [$user->currentTeam, $routine]), ['name' => 'Brush teeth'])
        ->assertNotFound();
});

test('store returns 404 when the routine belongs to a different team than the url', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->members()->attach($user, ['role' => TeamRole::Member->value]);
    $routine = Routine::factory()->for($team)->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.steps.store', [$user->currentTeam, $routine]), ['name' => 'Brush teeth'])
        ->assertNotFound();
});

test('store returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.routines.steps.store', [$routine->team, $routine]), ['name' => 'Brush teeth'])
        ->assertForbidden();
});

test('update renames a step', function () {
    $user = User::factory()->create();
    $step = RoutineStep::factory()->for(Routine::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.steps.update', [$user->currentTeam, $step->routine, $step]), ['name' => 'Floss'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Floss');
});

test('update returns 404 when the step belongs to a different routine than the url', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $step = RoutineStep::factory()->for(Routine::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.steps.update', [$user->currentTeam, $routine, $step]), ['name' => 'Floss'])
        ->assertNotFound();
});

test('update validates the step', function () {
    $user = User::factory()->create();
    $step = RoutineStep::factory()->for(Routine::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.steps.update', [$user->currentTeam, $step->routine, $step]), ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
});

test('update returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $step = RoutineStep::factory()->create();

    $this->actingAs($user)
        ->patchJson(route('api.routines.steps.update', [$step->routine->team, $step->routine, $step]), ['name' => 'Floss'])
        ->assertForbidden();
});

test('destroy deletes a step', function () {
    $user = User::factory()->create();
    $step = RoutineStep::factory()->for(Routine::factory()->for($user->currentTeam))->create();

    $this->actingAs($user)
        ->deleteJson(route('api.routines.steps.destroy', [$user->currentTeam, $step->routine, $step]))
        ->assertNoContent();

    $this->assertSoftDeleted('routine_steps', ['id' => $step->id]);
});

test('destroy returns 403 for a team the user does not belong to', function () {
    $user = User::factory()->create();
    $step = RoutineStep::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.routines.steps.destroy', [$step->routine->team, $step->routine, $step]))
        ->assertForbidden();
});
