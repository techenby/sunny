<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\GetRoutine;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use Illuminate\Testing\Fluent\AssertableJson;

test('it returns a routine with its steps in order', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create(['name' => 'Bedtime']);
    $second = RoutineStep::factory()->for($routine)->create(['name' => 'Read a story', 'position' => 2]);
    $first = RoutineStep::factory()->for($routine)->create(['name' => 'Brush teeth', 'position' => 1]);
    RoutineStep::factory()->for($routine)->create(['name' => 'Removed'])->delete();

    SunnyServer::actingAs($user)
        ->tool(GetRoutine::class, ['id' => $routine->id])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $routine->id)
            ->where('name', 'Bedtime')
            ->where('schedule_summary', 'Every day')
            ->where('step_count', 2)
            ->where('steps', [
                ['id' => $first->id, 'name' => 'Brush teeth', 'position' => 1],
                ['id' => $second->id, 'name' => 'Read a story', 'position' => 2],
            ])
            ->etc());
});

test('it requires an id', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetRoutine::class)
        ->assertHasErrors();
});

test('it does not return routines from other teams', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetRoutine::class, ['id' => $routine->id])
        ->assertHasErrors(['Routine not found.']);
});
