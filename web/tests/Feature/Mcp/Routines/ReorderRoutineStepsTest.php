<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\ReorderRoutineSteps;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;

test('it reorders the steps', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $first = RoutineStep::factory()->for($routine)->create(['name' => 'First', 'position' => 1]);
    $second = RoutineStep::factory()->for($routine)->create(['name' => 'Second', 'position' => 2]);
    $third = RoutineStep::factory()->for($routine)->create(['name' => 'Third', 'position' => 3]);

    SunnyServer::actingAs($user)
        ->tool(ReorderRoutineSteps::class, [
            'routine_id' => $routine->id,
            'step_ids' => [$third->id, $first->id, $second->id],
        ])
        ->assertOk()
        ->assertStructuredContent([
            'routine_id' => $routine->id,
            'steps' => [
                ['id' => $third->id, 'name' => 'Third', 'position' => 1],
                ['id' => $first->id, 'name' => 'First', 'position' => 2],
                ['id' => $second->id, 'name' => 'Second', 'position' => 3],
            ],
        ]);

    expect($routine->steps()->pluck('name')->all())->toBe(['Third', 'First', 'Second']);
});

test('it requires every current step exactly once', function (array $stepIndexes) {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $steps = RoutineStep::factory()->for($routine)->count(3)->sequence(
        ['position' => 1],
        ['position' => 2],
        ['position' => 3],
    )->create();
    $foreign = RoutineStep::factory()->create();

    $stepIds = collect($stepIndexes)
        ->map(fn ($index) => $index === 'foreign' ? $foreign->id : $steps[$index]->id)
        ->all();

    SunnyServer::actingAs($user)
        ->tool(ReorderRoutineSteps::class, ['routine_id' => $routine->id, 'step_ids' => $stepIds])
        ->assertHasErrors();

    expect($routine->steps()->pluck('position')->all())->toBe([1, 2, 3]);
})->with([
    'missing a step' => [[2, 0]],
    'a duplicate step' => [[2, 0, 0]],
    'an extra step' => [[2, 0, 1, 'foreign']],
    'a step from elsewhere' => [[2, 0, 'foreign']],
]);

test('it names the expected step ids', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();
    $step = RoutineStep::factory()->for($routine)->create();

    SunnyServer::actingAs($user)
        ->tool(ReorderRoutineSteps::class, ['routine_id' => $routine->id, 'step_ids' => [$step->id + 1]])
        ->assertHasErrors(["exactly the routine's current step ids, each once: [{$step->id}]"]);
});

test('it cannot reorder steps on routines from other teams', function () {
    $user = User::factory()->create();
    $step = RoutineStep::factory()->create(['position' => 4]);

    SunnyServer::actingAs($user)
        ->tool(ReorderRoutineSteps::class, ['routine_id' => $step->routine_id, 'step_ids' => [$step->id]])
        ->assertHasErrors(['Routine not found.']);

    expect($step->refresh()->position)->toBe(4);
});
