<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Livewire\Livewire;

test('it shows my lists and household lists with incomplete items', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $other = User::factory()->memberOf($team)->create();

    $mine = Checklist::factory()->for($team)->todo()->ownedBy($user)->create(['name' => 'My errands']);
    $household = Checklist::factory()->for($team)->shopping()->household()->create(['name' => 'Groceries']);
    $theirs = Checklist::factory()->for($team)->todo()->ownedBy($other)->create(['name' => 'Their errands']);

    foreach ([$mine, $household, $theirs] as $list) {
        ChecklistItem::factory()->for($list)->create();
    }

    Livewire::actingAs($user)
        ->test('dashboard.lists')
        ->assertSee('My errands')
        ->assertSee('Groceries')
        ->assertDontSee('Their errands');
});

test('it hides wish lists, finished lists, and completed items', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;

    $wishlist = Checklist::factory()->for($team)->wishlist()->household()->create(['name' => 'Birthday']);
    ChecklistItem::factory()->for($wishlist)->create();

    $finished = Checklist::factory()->for($team)->todo()->household()->create(['name' => 'Done list']);
    ChecklistItem::factory()->for($finished)->completed()->create();

    $groceries = Checklist::factory()->for($team)->shopping()->household()->create(['name' => 'Groceries']);
    ChecklistItem::factory()->for($groceries)->create(['name' => 'Oat milk']);
    ChecklistItem::factory()->for($groceries)->completed()->create(['name' => 'Bananas']);

    Livewire::actingAs($user)
        ->test('dashboard.lists')
        ->assertDontSee('Birthday')
        ->assertDontSee('Done list')
        ->assertSee('Oat milk')
        ->assertDontSee('Bananas');
});

test('it caps the items shown per list', function () {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->todo()->household()->create();
    ChecklistItem::factory()->for($list)->count(7)->create();

    Livewire::actingAs($user)
        ->test('dashboard.lists')
        ->assertSee(__(':count more', ['count' => 2]));
});

test('it shows an empty state when nothing is left', function () {
    $user = User::factory()->create();

    Livewire::actingAs($user)
        ->test('dashboard.lists')
        ->assertSee(__('All caught up. Nothing left on your lists.'));
});

test('toggling an item completes it and drops it from the card', function () {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->todo()->household()->create();
    $item = ChecklistItem::factory()->for($list)->create(['name' => 'Call the plumber']);

    Livewire::actingAs($user)
        ->test('dashboard.lists')
        ->call('toggle', $item->id)
        ->assertDontSee('Call the plumber');

    expect($item->fresh()->isCompleted())->toBeTrue()
        ->and($item->fresh()->completed_by)->toBe($user->id);
});

test('it will not toggle an item from another team', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create();

    Livewire::actingAs($user)
        ->test('dashboard.lists')
        ->call('toggle', $item->id)
        ->assertForbidden();
});
