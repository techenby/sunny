<?php

use App\Mcp\Prompts\PlanMeals;
use App\Mcp\Servers\SunnyServer;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-05 08:00', 'America/Chicago'));
});

test('it plans a week of meals starting today in the team timezone', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->prompt(PlanMeals::class)
        ->assertOk()
        ->assertSee([
            'for 7 day(s), from Tuesday, May 5 through Monday, May 11, 2026 (America/Chicago)',
            'get-calendar-events with from "2026-05-05" and days 7',
            'search-recipes',
            'add-checklist-items',
            'create-checklist',
        ])
        ->assertDontSee('Keep these preferences in mind');
});

test('it accepts the number of days and preferences', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->prompt(PlanMeals::class, ['days' => '3', 'preferences' => 'vegetarian on Mondays'])
        ->assertOk()
        ->assertSee([
            'for 3 day(s), from Tuesday, May 5 through Thursday, May 7, 2026',
            'Keep these preferences in mind: vegetarian on Mondays',
        ]);
});

test('it validates the number of days', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->prompt(PlanMeals::class, ['days' => '30'])
        ->assertHasErrors(['The number of days must be a whole number between 1 and 14.']);
});
