<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Routines\ListRoutines;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;

test('it lists the routines on the current team', function () {
    $user = User::factory()->create(['name' => 'Ada']);
    $morning = Routine::factory()->for($user->currentTeam)->assignedTo($user)->weekly([Carbon::MONDAY, Carbon::FRIDAY])->create([
        'name' => 'Morning routine',
        'starts_on' => '2026-09-01',
    ]);
    RoutineStep::factory()->for($morning)->count(2)->create();
    Routine::factory()->for($user->currentTeam)->household()->monthly(15)->inactive()->create(['name' => 'Change filters']);
    Routine::factory()->create(['name' => 'Other team routine']);

    SunnyServer::actingAs($user)
        ->tool(ListRoutines::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('count', 2)
            ->has('routines', 2)
            ->has('routines.0', fn (AssertableJson $json) => $json
                ->where('name', 'Change filters')
                ->where('user_id', null)
                ->where('owner_name', null)
                ->where('frequency', 'monthly')
                ->where('weekdays', null)
                ->where('day_of_month', 15)
                ->where('is_active', false)
                ->where('schedule_summary', 'Monthly on the 15th')
                ->where('step_count', 0)
                ->etc())
            ->has('routines.1', fn (AssertableJson $json) => $json
                ->where('id', $morning->id)
                ->where('name', 'Morning routine')
                ->where('user_id', $user->id)
                ->where('owner_name', 'Ada')
                ->where('time_of_day', 'morning')
                ->where('frequency', 'weekly')
                ->where('weekdays', [1, 5])
                ->where('day_of_month', null)
                ->where('starts_on', '2026-09-01')
                ->where('is_active', true)
                ->where('schedule_summary', 'Mon, Fri')
                ->where('step_count', 2)));
});

test('it returns an empty list when the team has no routines', function () {
    $user = User::factory()->create();
    Routine::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(ListRoutines::class)
        ->assertOk()
        ->assertStructuredContent(['count' => 0, 'routines' => []]);
});
