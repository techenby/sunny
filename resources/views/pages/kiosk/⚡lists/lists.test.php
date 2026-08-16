<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('kiosk.lists'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->assertOk();
})->group('smoke');

test('it shows the team lists and selects the first', function () {
    $user = User::factory()->create();
    Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries']);
    Checklist::factory()->for($user->currentTeam)->create(['name' => 'Hardware store']);

    $component = Livewire::actingAs($user)->test('pages::kiosk.lists');

    expect($component->get('lists'))->toHaveCount(2)
        ->and($component->get('list')->name)->toBe('Groceries');

    $component->assertSee('Groceries')->assertSee('Hardware store');
});

test('it does not show another team lists', function () {
    $user = User::factory()->create();
    Checklist::factory()->create(['name' => 'Someone else']);

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->assertDontSee('Someone else')
        ->assertSee(__('No lists yet'));
});

test('it adds an item to the selected list', function () {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->set('newItem', 'Oat milk')
        ->call('addItem')
        ->assertSet('newItem', '');

    expect($list->items()->pluck('name')->all())->toBe(['Oat milk']);
});

test('it ignores a blank item without erroring', function (string $name) {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->set('newItem', $name)
        ->call('addItem')
        ->assertHasNoErrors();

    expect($list->items()->count())->toBe(0);
})->with(['', '   ']);

test('it trims surrounding whitespace off an item', function () {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->set('newItem', '  Oat milk  ')
        ->call('addItem');

    expect($list->items()->sole()->name)->toBe('Oat milk');
});

test('it toggles an item and records who did it', function () {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($list)->create();

    $component = Livewire::actingAs($user)->test('pages::kiosk.lists');

    $component->call('toggle', $item->id);
    expect($item->fresh()->isCompleted())->toBeTrue()
        ->and($item->fresh()->completed_by)->toBe($user->id);

    $component->call('toggle', $item->id);
    expect($item->fresh()->isCompleted())->toBeFalse();
});

test('it removes an item', function () {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->create();
    $item = ChecklistItem::factory()->for($list)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->call('removeItem', $item->id);

    expect($list->items()->count())->toBe(0);
});

test('it clears completed items', function () {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->create();
    ChecklistItem::factory()->for($list)->count(2)->create();
    ChecklistItem::factory()->for($list)->completed()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->call('clearCompleted');

    expect($list->items()->count())->toBe(2);
});

test('it will not touch another team list', function () {
    $user = User::factory()->create();
    $item = ChecklistItem::factory()->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->call('toggle', $item->id)
        ->assertForbidden();

    expect($item->fresh()->isCompleted())->toBeFalse();
});

test('selecting a list switches the visible items', function () {
    $user = User::factory()->create();
    $groceries = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries']);
    $wishlist = Checklist::factory()->for($user->currentTeam)->wishlist()->create(['name' => 'Wishes']);

    ChecklistItem::factory()->for($groceries)->create(['name' => 'Oat milk']);
    ChecklistItem::factory()->for($wishlist)->create(['name' => 'Telescope']);

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->assertSee('Oat milk')
        ->assertDontSee('Telescope')
        ->call('select', $wishlist->id)
        ->assertSee('Telescope')
        ->assertDontSee('Oat milk');
});

test('an owned list shows its owner', function () {
    $user = User::factory()->create();
    Checklist::factory()->for($user->currentTeam)->ownedBy($user)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->assertSee($user->name);
});

test('an item name longer than the column is rejected', function () {
    $user = User::factory()->create();
    Checklist::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->set('newItem', str_repeat('a', 256))
        ->call('addItem')
        ->assertHasErrors(['newItem' => 'max']);

    expect(ChecklistItem::count())->toBe(0);
});

test('an item name at the limit is accepted', function () {
    $user = User::factory()->create();
    Checklist::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::kiosk.lists')
        ->set('newItem', str_repeat('a', 255))
        ->call('addItem')
        ->assertHasNoErrors();

    expect(ChecklistItem::count())->toBe(1);
});
