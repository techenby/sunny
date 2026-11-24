<?php

use App\Enums\TeamRole;
use App\Models\Team;
use App\Models\User;
use Livewire\Mechanisms\HandleRequests\EndpointResolver;

test('a page left open stops working once its user is removed from the team', function (bool $removed, int $status) {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $user->teams()->attach($team, ['role' => TeamRole::Member]);
    $snapshot = livewireSnapshotFrom($this->actingAs($user)->get(route('lists.index', ['current_team' => $team->slug]))->assertOk()->getContent(), 'lists.index');

    $removed && $user->teams()->detach($team);

    $this->actingAs($user->fresh())
        ->withHeader('X-Livewire', 'true')
        ->postJson(EndpointResolver::updatePath(), ['components' => [['snapshot' => $snapshot, 'updates' => [], 'calls' => []]]])
        ->assertStatus($status);
})->with([
    'removed' => [true, 403],
    'still a member' => [false, 200],
]);
