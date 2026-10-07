<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\UpdateRoutine;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;

test('it updates the provided fields', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create(['name' => 'Morning']);
    RoutineStep::factory()->for($routine)->create(['name' => 'Brush teeth']);

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutine::class, [
            'id' => $routine->id,
            'name' => 'Morning routine',
            'user_id' => $user->id,
            'time_of_day' => 'afternoon',
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('id', $routine->id)
            ->where('name', 'Morning routine')
            ->where('user_id', $user->id)
            ->where('time_of_day', 'afternoon')
            ->where('frequency', 'daily')
            ->where('steps.0.name', 'Brush teeth')
            ->etc());

    expect($routine->refresh()->name)->toBe('Morning routine');
});

test('it pauses and resumes a routine', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutine::class, ['id' => $routine->id, 'is_active' => false])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('is_active', false)->etc());

    expect($routine->refresh()->is_active)->toBeFalse();

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutine::class, ['id' => $routine->id, 'is_active' => true])
        ->assertOk();

    expect($routine->refresh()->is_active)->toBeTrue();
});

test('it clears schedule fields the new frequency does not use', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->weekly([Carbon::MONDAY])->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutine::class, ['id' => $routine->id, 'frequency' => 'monthly', 'day_of_month' => 3])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('frequency', 'monthly')
            ->where('weekdays', null)
            ->where('day_of_month', 3)
            ->where('schedule_summary', 'Monthly on the 3rd')
            ->etc());

    expect($routine->refresh()->weekdays)->toBeNull();
});

test('it requires weekdays when switching to weekly', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->daily()->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutine::class, ['id' => $routine->id, 'frequency' => 'weekly'])
        ->assertHasErrors(['0 is Sunday and 6 is Saturday']);
});

test('it requires at least one field', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutine::class, ['id' => $routine->id])
        ->assertHasErrors(['Provide at least one field to update.']);
});

test('it cannot assign a routine to someone outside the team', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutine::class, ['id' => $routine->id, 'user_id' => User::factory()->create()->id])
        ->assertHasErrors(['The user_id must be a member of the current team']);

    expect($routine->refresh()->user_id)->toBeNull();
});

test('it cannot update routines from other teams', function () {
    $user = User::factory()->create();
    $routine = Routine::factory()->create(['name' => 'Theirs']);

    SunnyServer::actingAs($user)
        ->tool(UpdateRoutine::class, ['id' => $routine->id, 'name' => 'Mine'])
        ->assertHasErrors(['Routine not found.']);

    expect($routine->refresh()->name)->toBe('Theirs');
});
