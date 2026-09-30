<?php

use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;

test('guests cannot access routines', function () {
    $this->getJson(route('api.routines.index', 'household'))->assertUnauthorized();
    $this->getJson(route('api.routines.show', ['household', 1]))->assertUnauthorized();
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
