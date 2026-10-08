<?php

use App\Enums\TeamRole;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;

test('guests are redirected to the login page and back to the link', function () {
    $item = Item::factory()->create();

    $this->get(route('inventory.link', $item))
        ->assertRedirect(route('login'));

    expect(session('url.intended'))->toBe(route('inventory.link', $item));
});

test('a link to an item without children opens the item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('inventory.link', $item))
        ->assertRedirect(route('inventory.show', ['current_team' => $user->currentTeam, 'item' => $item]));
});

test('a link to an item with children opens its contents', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->for($user->currentTeam)->bin()->create();
    Item::factory()->childOf($bin)->create();

    $this->actingAs($user)
        ->get(route('inventory.link', $bin))
        ->assertRedirect(route('inventory.index', ['current_team' => $user->currentTeam, 'parentId' => $bin->id]));
});

test('a link to an item whose children are all trashed opens the item', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->for($user->currentTeam)->bin()->create();
    Item::factory()->childOf($bin)->create()->delete();

    $this->actingAs($user)
        ->get(route('inventory.link', $bin))
        ->assertRedirect(route('inventory.show', ['current_team' => $user->currentTeam, 'item' => $bin]));
});

test('a link to a trashed item opens the item', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->for($user->currentTeam)->bin()->create();
    Item::factory()->childOf($bin)->create();
    $bin->delete();

    $this->actingAs($user)
        ->get(route('inventory.link', $bin))
        ->assertRedirect(route('inventory.show', ['current_team' => $user->currentTeam, 'item' => $bin]));
});

test('a link keeps working after the team is renamed', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $link = route('inventory.link', $item);

    $user->currentTeam->update(['name' => 'Going Merry']);

    $this->actingAs($user)
        ->get($link)
        ->assertRedirect(route('inventory.show', ['current_team' => $user->currentTeam->fresh(), 'item' => $item]));
});

test('a link to an item in another of the user\'s teams switches to that team', function () {
    $user = User::factory()->create();
    $team = Team::factory()->create();
    $team->memberships()->create(['user_id' => $user->id, 'role' => TeamRole::Member]);
    $item = Item::factory()->for($team)->create();

    $this->actingAs($user)
        ->followingRedirects()
        ->get(route('inventory.link', $item))
        ->assertOk();

    expect($user->fresh()->current_team_id)->toBe($team->id);
});

test('cannot open a link to an item from a team the user is not on', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create();

    $this->actingAs($user)
        ->get(route('inventory.link', $item))
        ->assertForbidden();
});
