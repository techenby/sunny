<?php

use App\Enums\TeamRole;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;

test('guests cannot delete an account', function () {
    $this->deleteJson(route('api.user.destroy'), ['password' => 'password'])->assertUnauthorized();
});

test('a user can delete their account with their password', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $item = Item::factory()->for($team)->create();

    $this->actingAs($user)
        ->deleteJson(route('api.user.destroy'), ['password' => 'password'])
        ->assertNoContent();

    expect($user->fresh())->toBeNull()
        ->and(Team::withTrashed()->find($team->id))->toBeNull()
        ->and(Item::withTrashed()->find($item->id))->toBeNull();
});

test('the account is kept when the password is wrong or missing', function (array $payload) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->deleteJson(route('api.user.destroy'), $payload)
        ->assertUnprocessable()
        ->assertJsonValidationErrors('password');

    expect($user->fresh())->not->toBeNull();
})->with([
    'wrong' => [['password' => 'wrong-password']],
    'missing' => [[]],
]);

test('the account is kept while the user owns a team other people use', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['name' => 'Straw Hats']);
    $team->memberships()->create(['user_id' => User::factory()->create()->id, 'role' => TeamRole::Member]);

    $this->actingAs($user)
        ->deleteJson(route('api.user.destroy'), ['password' => 'password'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('teams')
        ->assertJsonPath('errors.teams.0', $user->deletionBlockedMessage());

    expect($user->fresh())->not->toBeNull()
        ->and($team->fresh()->members)->toHaveCount(2);
});
