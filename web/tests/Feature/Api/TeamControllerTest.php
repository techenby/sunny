<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;

beforeEach(function () {
    $this->luffy = User::factory()->create(['name' => 'Luffy']);
    $this->nami = User::factory()->create(['name' => 'Nami']);
    $this->zoro = User::factory()->create(['name' => 'Zoro']);
    $this->crew = Team::factory()->create(['name' => 'Straw Hats']);
    $this->crew->memberships()->create(['user_id' => $this->luffy->id, 'role' => TeamRole::Owner]);
    $this->crew->memberships()->create(['user_id' => $this->nami->id, 'role' => TeamRole::Admin]);
    $this->crew->memberships()->create(['user_id' => $this->zoro->id, 'role' => TeamRole::Member]);
});

test('guests cannot manage teams', function () {
    $this->postJson(route('api.teams.store'), ['name' => 'Going Merry'])->assertUnauthorized();
    $this->patchJson(route('api.teams.update', 'household'), ['name' => 'Going Merry'])->assertUnauthorized();
    $this->postJson(route('api.teams.leave', 'household'))->assertUnauthorized();
});

test('a user can create a team they own without changing their current team', function () {
    $this->actingAs($this->zoro)
        ->postJson(route('api.teams.store'), ['name' => 'Going Merry'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Going Merry')
        ->assertJsonPath('data.slug', 'going-merry')
        ->assertJsonPath('data.is_personal', false)
        ->assertJsonPath('data.timezone', 'America/Chicago')
        ->assertJsonPath('data.members', [['id' => $this->zoro->id, 'name' => 'Zoro', 'initials' => 'Z', 'role' => 'owner']]);

    $team = Team::firstWhere('name', 'Going Merry');

    expect($this->zoro->fresh())
        ->ownsTeam($team)->toBeTrue()
        ->current_team_id->toBe($this->zoro->current_team_id);
});

test('a new team name is validated like the web', function (string $name) {
    $this->actingAs($this->zoro)
        ->postJson(route('api.teams.store'), ['name' => $name])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('name');
})->with([
    'blank' => [''],
    'too long' => [str_repeat('a', 256)],
    'reserved' => ['settings'],
]);

test('owners and admins can rename a team and change its household settings', function (string $user) {
    $this->actingAs($this->{$user})
        ->patchJson(route('api.teams.update', $this->crew), [
            'name' => 'Thousand Sunny',
            'timezone' => 'Asia/Tokyo',
            'week_start' => 1,
            'address' => ['address' => '1 Sunny Lane', 'city' => 'Water 7', 'state' => 'Grand Line', 'zip' => '77777', 'lat' => 35.6, 'long' => 139.7],
        ])
        ->assertOk()
        ->assertJsonPath('data.slug', 'thousand-sunny')
        ->assertJsonPath('data.timezone', 'Asia/Tokyo')
        ->assertJsonPath('data.week_start', 1)
        ->assertJsonPath('data.address.lat', '35.6')
        ->assertJsonCount(3, 'data.members');

    expect($this->crew->fresh())
        ->name->toBe('Thousand Sunny')
        ->timezone->toBe('Asia/Tokyo')
        ->week_start->toBe(1)
        ->address->toBe(['address' => '1 Sunny Lane', 'city' => 'Water 7', 'state' => 'Grand Line', 'zip' => '77777', 'lat' => '35.6', 'long' => '139.7']);
})->with(['luffy', 'nami']);

test('a team update can leave out fields it does not change', function () {
    $this->actingAs($this->nami)
        ->patchJson(route('api.teams.update', $this->crew), ['week_start' => 1])
        ->assertOk()
        ->assertJsonPath('data.name', 'Straw Hats');
});

test('members cannot change a team', function () {
    $this->actingAs($this->zoro)
        ->patchJson(route('api.teams.update', $this->crew), ['name' => 'Zoro Pirates'])
        ->assertForbidden();

    expect($this->crew->fresh()->name)->toBe('Straw Hats');
});

test('people outside a team cannot change it', function () {
    $this->actingAs(User::factory()->create())
        ->patchJson(route('api.teams.update', $this->crew), ['name' => 'Baroque Works'])
        ->assertForbidden();
});

test('team changes are validated', function (array $payload, string $error) {
    $this->actingAs($this->luffy)
        ->patchJson(route('api.teams.update', $this->crew), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors($error);
})->with([
    'blank name' => [['name' => ''], 'name'],
    'reserved name' => [['name' => 'admin'], 'name'],
    'unknown timezone' => [['timezone' => 'Mars/Olympus'], 'timezone'],
    'week start out of range' => [['week_start' => 7], 'week_start'],
    'incomplete address' => [['address' => ['address' => '1 Sunny Lane']], 'address.city'],
    'latitude out of range' => [['address' => ['address' => '1 Sunny Lane', 'city' => 'Water 7', 'state' => 'Grand Line', 'zip' => '77777', 'lat' => 91, 'long' => 0]], 'address.lat'],
]);

test('members and admins can leave a team', function (string $user) {
    $this->actingAs($this->{$user})
        ->postJson(route('api.teams.leave', $this->crew))
        ->assertNoContent();

    expect($this->{$user}->belongsToTeam($this->crew))->toBeFalse();
})->with(['nami', 'zoro']);

test('leaving the current team switches to the personal team even when another team sorts first', function () {
    Team::factory()->create(['name' => 'Alabasta'])->memberships()->create(['user_id' => $this->zoro->id, 'role' => TeamRole::Member]);
    $this->zoro->switchTeam($this->crew);

    $this->actingAs($this->zoro)
        ->postJson(route('api.teams.leave', $this->crew))
        ->assertNoContent();

    expect($this->zoro->fresh()->current_team_id)->toBe($this->zoro->personalTeam()->id);
});

test('owners cannot leave their team and nobody can leave a personal team or a team they are not in', function (Closure $team) {
    $this->actingAs($this->luffy)
        ->postJson(route('api.teams.leave', $team($this)))
        ->assertForbidden();
})->with([
    'owned team' => [fn ($test) => $test->crew],
    'personal team' => [fn ($test) => $test->luffy->personalTeam()],
    'another team' => [fn ($test) => Team::factory()->create()],
]);

test('a user can only own so many teams', function () {
    Team::factory()->count(Team::MAX_OWNED_PER_USER - $this->zoro->ownedTeams()->count())
        ->create()
        ->each(fn (Team $team) => $team->memberships()->create(['user_id' => $this->zoro->id, 'role' => TeamRole::Owner]));

    $this->actingAs($this->zoro)
        ->postJson(route('api.teams.store'), ['name' => 'One Too Many'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'You can own up to 10 teams.');

    expect(Team::where('name', 'One Too Many')->exists())->toBeFalse();
});
