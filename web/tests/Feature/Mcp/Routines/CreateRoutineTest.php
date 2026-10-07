<?php

use App\Mcp\Tools\Routines\CreateRoutine;
use App\Models\Routine;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('it creates a routine with steps starting today in the team timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-09-30 23:30', 'America/Chicago'));

    $user = User::factory()->create(['name' => 'Ada']);

    SunnyTestServer::actingAs($user)
        ->tool(CreateRoutine::class, [
            'name' => 'Morning routine',
            'user_id' => $user->id,
            'time_of_day' => 'morning',
            'frequency' => 'weekly',
            'weekdays' => [1, 3, 5],
            'day_of_month' => 12,
            'steps' => ['Brush teeth', 'Get dressed'],
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('name', 'Morning routine')
            ->where('user_id', $user->id)
            ->where('owner_name', 'Ada')
            ->where('time_of_day', 'morning')
            ->where('frequency', 'weekly')
            ->where('weekdays', [1, 3, 5])
            ->where('day_of_month', null)
            ->where('starts_on', '2026-09-30')
            ->where('is_active', true)
            ->where('schedule_summary', 'Mon, Wed, Fri')
            ->where('step_count', 2)
            ->where('steps.0.name', 'Brush teeth')
            ->where('steps.0.position', 1)
            ->where('steps.1.name', 'Get dressed')
            ->where('steps.1.position', 2)
            ->etc());

    $routine = $user->currentTeam->routines()->sole();

    expect($routine->day_of_month)->toBeNull()
        ->and($routine->steps)->toHaveCount(2);
});

test('it creates a paused household routine with a start date', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(CreateRoutine::class, [
            'name' => 'Change filters',
            'time_of_day' => 'anytime',
            'frequency' => 'monthly',
            'day_of_month' => 31,
            'starts_on' => '2026-11-01',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('user_id', null)
            ->where('owner_name', null)
            ->where('day_of_month', 31)
            ->where('weekdays', null)
            ->where('starts_on', '2026-11-01')
            ->where('is_active', false)
            ->where('steps', [])
            ->etc());
});

test('it requires weekdays for weekly routines', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(CreateRoutine::class, [
            'name' => 'Trash day',
            'time_of_day' => 'evening',
            'frequency' => 'weekly',
        ])
        ->assertHasErrors(['0 is Sunday and 6 is Saturday']);

    expect(Routine::count())->toBe(0);
});

test('it rejects invalid weekdays', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(CreateRoutine::class, [
            'name' => 'Trash day',
            'time_of_day' => 'evening',
            'frequency' => 'weekly',
            'weekdays' => [7],
        ])
        ->assertHasErrors(['Weekdays must be integers where 0 is Sunday and 6 is Saturday.']);
});

test('it requires a day of month for monthly routines', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(CreateRoutine::class, [
            'name' => 'Pay rent',
            'time_of_day' => 'anytime',
            'frequency' => 'monthly',
        ])
        ->assertHasErrors(['Monthly routines need a day_of_month']);
});

test('it requires a name, time of day and frequency', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(CreateRoutine::class)
        ->assertHasErrors();
});

test('it cannot assign a routine to someone outside the team', function () {
    $user = User::factory()->create();
    $outsider = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(CreateRoutine::class, [
            'name' => 'Morning routine',
            'user_id' => $outsider->id,
            'time_of_day' => 'morning',
            'frequency' => 'daily',
        ])
        ->assertHasErrors(['The user_id must be a member of the current team']);

    expect(Routine::count())->toBe(0);
});
