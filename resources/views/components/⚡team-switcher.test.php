<?php

use App\Models\Team;
use App\Models\User;
use Livewire\Livewire;

test('renders successfully', function () {
    $user = User::factory()->withTeam()->create(['name' => 'Monkey D. Luffy']);

    Livewire::actingAs($user)
        ->test('team-switcher')
        ->assertOk()
        ->assertSee('Monkey D. Luffy\'s Team');
});

test('can switch teams', function () {
    $user = User::factory()
        ->withTeam('Baroque Works')
        ->has(
            Team::factory()->state(['name' => 'Strawhat Pirates'])
        )
        ->create(['name' => 'Nico Robin']);

    [$baroque, $strawhats] = $user->teams->sortBy('name')->values();

    expect($user)->current_team_id->toBe($baroque->id);

    Livewire::actingAs($user)
        ->test('team-switcher')
        ->assertOk()
        ->assertSeeInOrder(['Baroque Works', 'Strawhat Pirates', 'Baroque Works'])
        ->assertSet('currentTeamId', $baroque->id)
        ->set('currentTeamId', $strawhats->id)
        ->assertSet('currentTeam.id', $strawhats->id)
        ->assertSeeInOrder(['Strawhat Pirates', 'Strawhat Pirates', 'Baroque Works']);

    expect($user->fresh())->current_team_id->toBe($strawhats->id);
});

test('can create new team', function () {
    $user = User::factory()
        ->withTeam('ASL')
        ->create(['name' => 'Monkey D. Luffy']);

    Livewire::actingAs($user)
        ->test('team-switcher')
        ->assertOk()
        ->assertSee('ASL')
        ->set('teamName', 'Strawhat Pirates')
        ->call('create')
        ->assertSet('teamName', '')
        ->assertSet('currentTeam.name', 'Strawhat Pirates')
        ->assertSet('currentTeamId', $user->fresh()->teams->firstWhere('name', 'Strawhat Pirates')->id);

    expect($user->fresh()->teams)->toHaveCount(2)
        ->and($user->currentTeam)->name->toBe('Strawhat Pirates');
});
