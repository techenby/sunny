<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\AddRoutineSteps;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it adds steps to the end of a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    RoutineStep::factory()->for($routine)->create(['name' => 'Wake up', 'position' => 1]);

    SunnyServer::actingAs($user)
        ->tool(AddRoutineSteps::class, [
            'routine_id' => $routine->id,
            'steps' => ['Brush teeth', 'Get dressed'],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('routine_id', $routine->id)
            ->has('steps', 2)
            ->where('steps.0.name', 'Brush teeth')
            ->where('steps.0.position', 2)
            ->where('steps.1.name', 'Get dressed')
            ->where('steps.1.position', 3)
            ->etc());

    expect($routine->steps()->pluck('name')->all())->toBe(['Wake up', 'Brush teeth', 'Get dressed']);
});

test('it requires at least one step', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(AddRoutineSteps::class, ['routine_id' => $routine->id, 'steps' => []])
        ->assertHasErrors(['array of strings']);
});

test('it cannot add steps to routines from other teams', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(AddRoutineSteps::class, ['routine_id' => $routine->id, 'steps' => ['Sneaky']])
        ->assertHasErrors(['Routine not found.']);

    expect($routine->steps()->count())->toBe(0);
});
