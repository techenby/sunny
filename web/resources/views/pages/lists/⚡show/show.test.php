<?php

use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $user = User::factory()->create();
    $list = Checklist::factory()->for($user->currentTeam)->create();

    actingAs($user)
        ->get(route('lists.show', $list))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::lists.show', ['checklist' => $list])
        ->assertOk();
})->group('smoke');

describe('authorization', function () {
    test('another team cannot open it', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->create();

        actingAs($user)
            ->get(route('lists.show', $list))
            ->assertForbidden();
    });

    test('will not touch an item from another list', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();
        $other = ChecklistItem::factory()->create();

        expect(fn () => Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->call('toggle', $other->id))
            ->toThrow(ModelNotFoundException::class);

        expect($other->fresh())->isCompleted()->toBeFalse();
    });
});

describe('form validation', function () {
    test('an item name longer than the column is rejected', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->set('newItem', str_repeat('a', 256))
            ->call('addItem')
            ->assertHasErrors(['newItem' => 'max']);

        expect($list->items())->count()->toBe(0);
    });

    test('an item name at the limit is accepted', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->set('newItem', str_repeat('a', 255))
            ->call('addItem')
            ->assertHasNoErrors();

        expect($list->items())->count()->toBe(1);
    });

    test('a blank item is ignored without erroring', function (string $name) {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->set('newItem', $name)
            ->call('addItem')
            ->assertHasNoErrors();

        expect($list->items())->count()->toBe(0);
    })->with(['', '   ']);

    test('surrounding whitespace is trimmed off an item', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->set('newItem', '  Oat milk  ')
            ->call('addItem');

        expect($list->items()->sole()->name)->toBe('Oat milk');
    });
});

describe('crud and actions', function () {
    test('adds items in order', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->set('newItem', 'Oat milk')
            ->call('addItem')
            ->assertSet('newItem', '')
            ->set('newItem', 'Coffee')
            ->call('addItem');

        expect($list->items()->pluck('name')->all())->toBe(['Oat milk', 'Coffee']);
    });

    test('toggles an item and records who did it', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();
        $item = ChecklistItem::factory()->for($list)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->call('toggle', $item->id);

        expect($item->fresh())
            ->isCompleted()->toBeTrue()
            ->completed_by->toBe($user->id);
    });

    test('removes an item', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();
        $item = ChecklistItem::factory()->for($list)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->call('removeItem', $item->id);

        expect($list->items())->count()->toBe(0);
    });

    test('clears completed items', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();
        ChecklistItem::factory()->for($list)->count(2)->create();
        ChecklistItem::factory()->for($list)->completed()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->call('clearCompleted');

        expect($list->items())->count()->toBe(2);
    });

    test('unchecks everything without deleting', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();
        ChecklistItem::factory()->for($list)->completed()->count(2)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.show', ['checklist' => $list])
            ->call('resetList');

        expect($list->items())
            ->count()->toBe(2)
            ->completed()->count()->toBe(0);
    });
});
