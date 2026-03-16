<?php

use App\Models\Item;
use App\Models\User;
use Livewire\Livewire;

test('can bulk delete selected items', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->currentTeam;

    $items = Item::factory()->count(3)->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->set('selected', $items->pluck('id')->all())
        ->call('delete')
        ->assertHasNoErrors()
        ->assertSet('selected', []);

    expect(Item::whereIn('id', $items->pluck('id'))->count())->toBe(0);
});

test('single delete still works with an id', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->currentTeam;

    $item = Item::factory()->create(['team_id' => $team->id]);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->call('delete', $item->id)
        ->assertHasNoErrors();

    expect(Item::find($item->id))->toBeNull();
});

test('can bulk restore selected trashed items', function () {
    $user = User::factory()->withTeam()->create();
    $team = $user->currentTeam;

    $items = Item::factory()->count(3)->create(['team_id' => $team->id]);
    $items->each->delete();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->set('selected', $items->pluck('id')->all())
        ->call('bulkRestore')
        ->assertHasNoErrors()
        ->assertSet('selected', []);

    expect(Item::whereIn('id', $items->pluck('id'))->count())->toBe(3);
});
