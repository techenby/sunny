<?php

use App\Enums\TimeOfDay;
use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\GetRoutineBoard;
use App\Models\Routine;
use App\Models\RoutineOccurrence;
use App\Models\RoutineStep;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-30 22:00', 'America/Chicago'));
});

test("it returns today's board in the team timezone", function () {
    $user = User::factory()->create(['name' => 'Ada']);
    $morning = Routine::factory()->for($user->currentTeam)->assignedTo($user)->daily()->create(['name' => 'Morning routine']);
    $makeBed = RoutineStep::factory()->for($morning)->create(['name' => 'Make bed', 'position' => 2]);
    RoutineStep::factory()->for($morning)->create(['name' => 'Get dressed', 'position' => 1]);
    Routine::factory()->for($user->currentTeam)->household()->daily()->timeOfDay(TimeOfDay::Evening)->create(['name' => 'Wind down']);
    Routine::factory()->for($user->currentTeam)->daily()->inactive()->create();
    Routine::factory()->daily()->create();

    $this->travelTo(CarbonImmutable::parse('2026-09-30 09:15', 'America/Chicago'));
    SunnyServer::actingAs($user)->tool(GetRoutineBoard::class)->assertOk();
    RoutineOccurrence::query()->firstWhere('routine_id', $morning->id)
        ->steps()->firstWhere('routine_step_id', $makeBed->id)
        ->complete($user);
    $this->travelTo(CarbonImmutable::parse('2026-09-30 22:00', 'America/Chicago'));

    SunnyServer::actingAs($user)
        ->tool(GetRoutineBoard::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('date', '2026-09-30')
            ->where('timezone', 'America/Chicago')
            ->has('occurrences', 2)
            ->has('occurrences.0', fn (AssertableJson $json) => $json
                ->where('routine_id', $morning->id)
                ->where('routine_name', 'Morning routine')
                ->where('time_of_day', 'morning')
                ->where('owner_name', 'Ada')
                ->where('due_on', '2026-09-30')
                ->where('progress', 50)
                ->where('completed', false)
                ->has('occurrence_id')
                ->has('steps', 2)
                ->has('steps.0', fn (AssertableJson $json) => $json
                    ->where('name', 'Get dressed')
                    ->where('completed', false)
                    ->where('completed_at', null)
                    ->where('completed_by', null)
                    ->where('completed_by_name', null)
                    ->has('occurrence_step_id'))
                ->has('steps.1', fn (AssertableJson $json) => $json
                    ->where('name', 'Make bed')
                    ->where('completed', true)
                    ->where('completed_at', '2026-09-30T09:15:00-05:00')
                    ->where('completed_by', $user->id)
                    ->where('completed_by_name', 'Ada')
                    ->has('occurrence_step_id')))
            ->where('occurrences.1.routine_name', 'Wind down')
            ->where('occurrences.1.owner_name', null)
            ->where('occurrences.1.steps', [])
            ->where('occurrences.1.progress', 0));
});

test('it returns the board for a given date', function () {
    $user = User::factory()->create();
    Routine::factory()->for($user->currentTeam)->weekly([Carbon::FRIDAY])->create(['name' => 'Trash day']);

    SunnyServer::actingAs($user)
        ->tool(GetRoutineBoard::class, ['date' => '2026-10-02'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('date', '2026-10-02')
            ->where('occurrences.0.routine_name', 'Trash day')
            ->where('occurrences.0.due_on', '2026-10-02')
            ->etc());

    SunnyServer::actingAs($user)
        ->tool(GetRoutineBoard::class, ['date' => '2026-10-01'])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json->where('occurrences', [])->etc());
});

test('it validates the date format', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetRoutineBoard::class, ['date' => 'next tuesday'])
        ->assertHasErrors(['Y-m-d format']);
});
