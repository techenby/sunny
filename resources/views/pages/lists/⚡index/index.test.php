<?php

use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\User;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;

test('renders successfully', function () {
    $user = User::factory()->create();

    actingAs($user)
        ->get(route('lists.index'))
        ->assertOk();

    Livewire::actingAs($user)
        ->test('pages::lists.index')
        ->assertOk();
})->group('smoke');

describe('authorization', function () {
    test('lists only the team lists', function () {
        $user = User::factory()->create();
        Checklist::factory()->for($user->currentTeam)->create(['name' => 'Groceries']);
        Checklist::factory()->create(['name' => 'Someone else']);

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->assertSee('Groceries')
            ->assertDontSee('Someone else');
    });

    test('will not assign a list to someone outside the team', function () {
        $user = User::factory()->create();
        $stranger = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('create')
            ->set('form.name', 'Wishes')
            ->set('form.user_id', $stranger->id)
            ->call('save')
            ->assertHasErrors('form.user_id');
    });

    test('will not edit another team list', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('edit', $list->id)
            ->assertForbidden();
    });
});

describe('form validation', function () {
    test('requires a name', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('create')
            ->set('form.name', '')
            ->call('save')
            ->assertHasErrors(['form.name' => 'required']);

        expect(Checklist::count())->toBe(0);
    });

    test('rejects a name longer than the column', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('create')
            ->set('form.name', str_repeat('a', 256))
            ->call('save')
            ->assertHasErrors(['form.name' => 'max']);

        expect(Checklist::count())->toBe(0);
    });

    test('rejects a type outside the enum', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('create')
            ->set('form.name', 'Groceries')
            ->set('form.type', 'errands')
            ->call('save')
            ->assertHasErrors('form.type');

        expect(Checklist::count())->toBe(0);
    });

    test('defaults a new list to the to-do type', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('create')
            ->assertSet('form.type', ChecklistType::Todo->value);
    });
});

describe('crud and actions', function () {
    test('creates a list and redirects to add items', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('create')
            ->set('form.name', 'Groceries')
            ->set('form.type', ChecklistType::Shopping->value)
            ->call('save')
            ->assertRedirect();

        $list = Checklist::sole();

        expect($list)
            ->name->toBe('Groceries')
            ->type->toBe(ChecklistType::Shopping)
            ->team_id->toBe($user->current_team_id);
    });

    test('creates a list owned by a member', function () {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('create')
            ->set('form.name', 'Wishes')
            ->set('form.type', ChecklistType::Wishlist->value)
            ->set('form.user_id', $user->id)
            ->call('save');

        expect(Checklist::sole())->user_id->toBe($user->id);
    });

    test('edits an existing list', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create(['name' => 'Old name']);
        ChecklistItem::factory()->for($list)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('edit', $list->id)
            ->assertSet('form.name', 'Old name')
            ->set('form.name', 'New name')
            ->call('save')
            ->assertNoRedirect();

        expect($list->fresh())->name->toBe('New name');
    });

    test('deletes a list', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::lists.index')
            ->call('delete', $list->id);

        expect($list->fresh())->toBeTrashed();
    });

    test('counts completed items', function () {
        $user = User::factory()->create();
        $list = Checklist::factory()->for($user->currentTeam)->create();
        ChecklistItem::factory()->for($list)->count(2)->create();
        ChecklistItem::factory()->for($list)->completed()->create();

        $lists = Livewire::actingAs($user)->test('pages::lists.index')->get('lists');

        expect($lists->first())
            ->items_count->toBe(3)
            ->completed_items_count->toBe(1);
    });
});
