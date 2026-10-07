<?php

use App\Mcp\Prompts\MorningCheckIn;
use App\Mcp\Servers\SunnyServer;
use App\Models\User;
use Carbon\CarbonImmutable;

test('it walks through routines, events, and to-dos for today in the team timezone', function () {
    $this->travelTo(CarbonImmutable::parse('2026-05-05 23:30', 'America/Chicago'));
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->prompt(MorningCheckIn::class)
        ->assertOk()
        ->assertSee([
            "for the {$user->currentTeam->name} household",
            'Today is Tuesday, May 5, 2026 (America/Chicago)',
            'get-routine-board',
            'get-calendar-events with days 1',
            'list-checklists with type "todo"',
        ]);
});
