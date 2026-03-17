<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $this->get(route('inventory.index'))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the items page', function () {
    $this->actingAs(User::factory()->withTeam()->create())
        ->get(route('inventory.index'))
        ->assertOk();
});

test('renders items for the current team only', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()->for($user->currentTeam)->create(['name' => 'Brown Hammer']);
    Item::factory()->create(['name' => 'Pink Hammer']);

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSee('Brown Hammer')
        ->assertDontSee('Pink Hammer');
});

test('can search items by name', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()
        ->count(2)
        ->for($user->currentTeam)
        ->sequence(
            ['name' => 'Hammer', 'parent_id' => null],
            ['name' => 'Screwdriver', 'parent_id' => null]
        )
        ->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSeeHtml('<span>Hammer</span>')
        ->assertSeeHtml('<span>Screwdriver</span>')
        ->set('search', 'Hammer')
        ->assertSeeHtml('<span>Hammer</span>')
        ->assertDontSeeHtml('<span>Screwdriver</span>');
});

test('can sort items', function () {
    $user = User::factory()->withTeam()->create();
    Item::factory()->count(2)->for($user->currentTeam)->sequence(['name' => 'Bravo'], ['name' => 'Alpha'])->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.index')
        ->assertSeeInOrder(['Alpha', 'Bravo'])
        ->call('sort', 'name')
        ->assertSeeInOrder(['Bravo', 'Alpha']);
});

describe('can navigate up and down', function () {
    test('can navigate down into a child item', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        Item::factory()->for($user->currentTeam)->bin()->childOf($parent)->create(['name' => 'Closet']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->assertSeeHtml('<span>Bedroom</span>')
            ->assertDontSeeHtml('<span>Closet</span>')
            ->call('navigateDown', $parent->id)
            ->assertSeeHtml('<span>Closet</span>')
            ->assertSet('parentId', $parent->id);
    });

    test('can navigate up from a child item', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        Item::factory()->for($user->currentTeam)->bin()->childOf($parent)->create(['name' => 'Closet']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('navigateDown', $parent->id)
            ->assertSee('Closet')
            ->call('navigateUp')
            ->assertSee('Bedroom');
    });

    test('breadcrumbs show full ancestor path', function () {
        $user = User::factory()->withTeam()->create();
        $bedroom = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        $closet = Item::factory()->for($user->currentTeam)->bin()->childOf($bedroom)->create(['name' => 'Right Closet']);
        $tote = Item::factory()->for($user->currentTeam)->bin()->childOf($closet)->create(['name' => 'Game Tote']);
        $game = Item::factory()->for($user->currentTeam)->bin()->childOf($tote)->create(['name' => 'Catan']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('navigateDown', $bedroom->id)
            ->call('navigateDown', $closet->id)
            ->call('navigateDown', $tote->id)
            ->assertSeeInOrder(['Inventory', 'Bedroom', 'Right Closet', 'Game Tote']);
    });

    test('breadcrumbs are empty at root level', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->assertSee('All')
            ->assertSet('parentId', null);
    });

    test('clicking a breadcrumb navigates to that level', function () {
        $user = User::factory()->withTeam()->create();
        $bedroom = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        $closet = Item::factory()->for($user->currentTeam)->bin()->childOf($bedroom)->create(['name' => 'Right Closet']);
        Item::factory()->for($user->currentTeam)->bin()->childOf($closet)->create(['name' => 'Game Tote']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('navigateDown', $bedroom->id)
            ->call('navigateDown', $closet->id)
            ->assertSeeHtml('<span>Game Tote</span>')
            ->call('navigateDown', $bedroom->id)
            ->assertSet('parentId', $bedroom->id)
            ->assertSeeHtml('<span>Right Closet</span>')
            ->assertDontSeeHtml('<span>Game Tote</span>');
    });

    test('clicking item without children redirects to show', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        $child = Item::factory()->for($user->currentTeam)->childOf($parent)->bin()->create(['name' => 'Closet']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('navigateDown', $parent->id)
            ->call('navigateDown', $child->id)
            ->assertRedirect(route('inventory.show', ['item' => $child]));
    });
});

describe('can create and edit', function () {
    test('create pre-fills parent_id with current parentId', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        Item::factory()->for($user->currentTeam)->childOf($parent)->bin()->create(['name' => 'Tote']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('navigateDown', $parent->id)
            ->call('create')
            ->assertSet('form.parent_id', $parent->id);
    });

    test('create does not pre-fill parent_id at root level', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('create')
            ->assertSet('form.parent_id', null);
    });

    test('create resets form before pre-filling parent_id', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        $item = Item::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Guitar']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('navigateDown', $parent->id)
            ->call('edit', $item->id)
            ->assertSet('form.name', 'Guitar')
            ->call('create')
            ->assertSet('form.name', '')
            ->assertSet('form.parent_id', $parent->id);
    });

    test('can create an item without a parent', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('form.name', 'Guitar')
            ->set('form.type', ItemType::Item)
            ->call('save')
            ->assertHasNoErrors();

        expect(Item::firstWhere('name', 'Guitar'))->not->toBeNull()
            ->team_id->toBe($user->current_team_id)
            ->parent_id->toBeNull();
    });

    test('can create an item with a parent', function () {
        $user = User::factory()->withTeam()->create();
        $location = Item::factory()->for($user->currentTeam)->create(['name' => 'Bedroom']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('form.name', 'Guitar')
            ->set('form.type', 'item')
            ->set('form.parent_id', $location->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('form.name', '')
            ->set('form.type', null)
            ->assertSet('form.parent_id', null);

        expect(Item::firstWhere('name', 'Guitar'))
            ->team_id->toBe($user->current_team_id)
            ->parent_id->toBe($location->id);
    });

    test('can edit an item', function () {
        $user = User::factory()->withTeam()->create();
        $bin = Item::factory()->for($user->currentTeam)->create(['name' => 'Soft Shell Case', 'type' => ItemType::Bin]);
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('edit', $item->id)
            ->assertSet('form.name', 'Guitar')
            ->assertSet('form.parent_id', null)
            ->set('form.name', 'Yamaha Guitar')
            ->set('form.parent_id', $bin->id)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('form.name', '')
            ->assertSet('form.parent_id', null);

        expect($item->fresh())
            ->name->toBe('Yamaha Guitar')
            ->parent_id->toBe($bin->id);
    });

    test('cannot edit an item from another team', function () {
        $user = User::factory()->withTeam()->create();
        $otherItem = Item::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('edit', $otherItem->id);
    })->throws(ModelNotFoundException::class);
});

describe('can delete', function () {
    test('can soft delete an item', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('delete', $item->id);

        expect($item->fresh()->trashed())->toBeTrue();
    });

    test('cannot delete an item from another team', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('delete', $item->id);
    })->throws(ModelNotFoundException::class);

    test('deleting a parent nullifies children parent_id', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create();
        $child = Item::factory()->for($user->currentTeam)->childOf($parent)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('delete', $parent->id);

        expect($child->fresh()->parent_id)->toBeNull();
    });
});

describe('can view and restore deleted items', function () {
    test('deleted items are hidden by default', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Deleted Widget']);
        $item->delete();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->assertDontSee('Deleted Widget');
    });

    test('can toggle to show deleted items', function () {
        $user = User::factory()->withTeam()->create();
        $active = Item::factory()->for($user->currentTeam)->create(['name' => 'Active Widget']);
        $deleted = Item::factory()->for($user->currentTeam)->create(['name' => 'Deleted Widget']);
        $deleted->delete();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->assertSeeHtml('<span>Active Widget</span>')
            ->assertDontSeeHtml('<span>Deleted Widget</span>')
            ->set('filters.showTrashed', true)
            ->assertSeeHtml('<span>Deleted Widget</span>')
            ->assertDontSeeHtml('<span>Active Widget</span>');
    });

    test('can restore a deleted item', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Restored Widget']);
        $item->delete();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('filters.showTrashed', true)
            ->assertSee('Restored Widget')
            ->call('restore', $item->id);

        expect($item->fresh())
            ->not->toBeNull()
            ->trashed()->toBeFalse();
    });

    test('cannot restore an item from another team', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->create();
        $item->delete();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('filters.showTrashed', true)
            ->call('restore', $item->id);
    })->throws(ModelNotFoundException::class);
});

describe('can permanently delete items', function () {
    test('can force delete a trashed item', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create();
        $item->delete();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('filters.showTrashed', true)
            ->call('forceDelete', $item->id);

        expect($item->fresh())->toBeNull();
    });

    test('cannot force delete an item from another team', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->create();
        $item->delete();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('filters.showTrashed', true)
            ->call('forceDelete', $item->id);
    })->throws(ModelNotFoundException::class);
});

describe('can add item metadata', function () {
    test('can create an item with metadata', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('create')
            ->set('form.name', 'Test Item')
            ->set('form.type', ItemType::Item->value)
            ->set('form.metadata', [
                ['key' => 'url', 'value' => 'https://amazon.com'],
                ['key' => 'price', 'value' => '$20'],
            ])
            ->call('save');

        $item = Item::where('name', 'Test Item')->first();

        expect($item)
            ->not->toBeNull()
            ->metadata->toBe([
                'url' => 'https://amazon.com',
                'price' => '$20',
            ]);
    });

    test('can update an item with metadata', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create([
            'metadata' => ['color' => 'red'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('edit', $item->id)
            ->set('form.metadata', [
                ['key' => 'color', 'value' => 'blue'],
                ['key' => 'size', 'value' => 'large'],
            ])
            ->call('save');

        expect($item->fresh()->metadata)->toBe([
            'color' => 'blue',
            'size' => 'large',
        ]);
    });

    test('empty metadata keys are filtered out', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('create')
            ->set('form.name', 'Filtered Item')
            ->set('form.type', ItemType::Item->value)
            ->set('form.metadata', [
                ['key' => '', 'value' => 'orphan'],
                ['key' => 'valid', 'value' => 'kept'],
            ])
            ->call('save');

        $item = Item::where('name', 'Filtered Item')->first();

        expect($item->metadata)->toBe(['valid' => 'kept']);
    });

    test('item with no metadata stores null', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('create')
            ->set('form.name', 'No Meta Item')
            ->set('form.type', ItemType::Item->value)
            ->call('save');

        $item = Item::where('name', 'No Meta Item')->first();

        expect($item->metadata)->toBeNull();
    });

    test('metadata value is required when key is present', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('create')
            ->set('form.name', 'Missing Value Item')
            ->set('form.type', ItemType::Item->value)
            ->set('form.metadata', [
                ['key' => 'color', 'value' => ''],
            ])
            ->call('save')
            ->assertHasErrors('form.metadata.0.value');
    });

    test('duplicate metadata keys are rejected', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('create')
            ->set('form.name', 'Dupe Keys Item')
            ->set('form.type', ItemType::Item->value)
            ->set('form.metadata', [
                ['key' => 'color', 'value' => 'red'],
                ['key' => 'color', 'value' => 'blue'],
            ])
            ->call('save')
            ->assertHasErrors('form.metadata.1.key');
    });

    test('editing an item loads existing metadata', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create([
            'metadata' => ['url' => 'https://example.com'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('edit', $item->id)
            ->assertSet('form.metadata', [
                ['key' => 'url', 'value' => 'https://example.com'],
            ]);
    });
});

describe('can manage item photos', function () {
    test('can create an item with a photo', function () {
        Storage::fake();
        $user = User::factory()->withTeam()->create();
        $photo = UploadedFile::fake()->image('guitar.jpg');

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('form.name', 'Guitar')
            ->set('form.type', ItemType::Item->value)
            ->set('form.photo', $photo)
            ->call('save')
            ->assertHasNoErrors();

        $item = Item::where('name', 'Guitar')->first();

        expect($item->photo_path)->not->toBeNull();
        Storage::assertExists($item->photo_path);
    });

    test('can create an item without a photo', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('form.name', 'Screwdriver')
            ->set('form.type', ItemType::Item->value)
            ->call('save')
            ->assertHasNoErrors();

        expect(Item::where('name', 'Screwdriver')->first()->photo_path)->toBeNull();
    });

    test('can update an item with a new photo', function () {
        Storage::fake();
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar', 'photo_path' => 'teams/1/items/guitar.jpg']);
        Storage::put('teams/1/items/guitar.jpg', 'old');

        $newPhoto = UploadedFile::fake()->image('new-guitar.png');

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('edit', $item->id)
            ->set('form.photo', $newPhoto)
            ->call('save')
            ->assertHasNoErrors();

        $item->refresh();

        expect($item->photo_path)->not->toBeNull();
        Storage::assertExists($item->photo_path);
        Storage::assertMissing('teams/1/items/guitar.jpg');
    });

    test('can remove an existing photo', function () {
        Storage::fake();
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar', 'photo_path' => 'teams/1/items/guitar.jpg']);
        Storage::put('teams/1/items/guitar.jpg', 'content');

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('edit', $item->id)
            ->call('removePhoto')
            ->call('save')
            ->assertHasNoErrors();

        expect($item->fresh()->photo_path)->toBeNull();
        Storage::assertMissing('teams/1/items/guitar.jpg');
    });

    test('photo validation rejects non-image files', function () {
        $user = User::factory()->withTeam()->create();
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('form.name', 'Guitar')
            ->set('form.type', ItemType::Item->value)
            ->set('form.photo', $file)
            ->call('save')
            ->assertHasErrors('form.photo');
    });
});

describe('can import items', function () {
    test('can import items from amazon csv', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('importForm.file', amazonFixtureUpload())
            ->call('import')
            ->assertHasNoErrors();

        expect($user->currentTeam->items)->toHaveCount(5);
    });

    test('import assigns items to the current parent', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Office']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('parentId', $parent->id)
            ->set('importForm.file', amazonFixtureUpload())
            ->call('import')
            ->assertHasNoErrors();

        expect($user->currentTeam->items()->where('parent_id', $parent->id)->count())->toBe(5);
    });

    test('import resets file after completion', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('importForm.file', amazonFixtureUpload())
            ->call('import')
            ->assertSet('importForm.file', null);
    });

    test('import requires a file', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('import')
            ->assertHasErrors('importForm.file');
    });

    test('import rejects non-csv files', function () {
        $user = User::factory()->withTeam()->create();
        $file = UploadedFile::fake()->image('photo.png');

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('importForm.file', $file)
            ->call('import')
            ->assertHasErrors('importForm.file');
    });
});

describe('bulk update parent', function () {
    test('can bulk update parent for selected items', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        $itemA = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);
        $itemB = Item::factory()->for($user->currentTeam)->create(['name' => 'Amp']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('selected', [$itemA->id, $itemB->id])
            ->set('bulkParentId', $parent->id)
            ->call('updateParent')
            ->assertHasNoErrors();

        expect($itemA->fresh()->parent_id)->toBe($parent->id)
            ->and($itemB->fresh()->parent_id)->toBe($parent->id);
    });

    test('can bulk move items to top level', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        $itemA = Item::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Guitar']);
        $itemB = Item::factory()->for($user->currentTeam)->childOf($parent)->create(['name' => 'Amp']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('selected', [$itemA->id, $itemB->id])
            ->set('bulkParentId', null)
            ->call('updateParent')
            ->assertHasNoErrors();

        expect($itemA->fresh()->parent_id)->toBeNull()
            ->and($itemB->fresh()->parent_id)->toBeNull();
    });

    test('bulk update resets selected and bulkParentId after success', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('selected', [$item->id])
            ->call('updateParent')
            ->assertSet('selected', [])
            ->assertSet('bulkParentId', null);
    });

    test('bulk parent options excludes selected items and their descendants', function () {
        $user = User::factory()->withTeam()->create();
        $forDeck = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'For Deck']);
        $tangerines = Item::factory()->for($user->currentTeam)->childOf($forDeck)->create(['name' => 'Tangerines']);
        $aftDeck = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Aft Deck']);

        $component = Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('selected', [$forDeck->id]);

        $options = $component->get('bulkParentOptions');

        expect($options->pluck('id')->toArray())
            ->toContain($aftDeck->id)
            ->not->toContain($forDeck->id)
            ->not->toContain($tangerines->id);
    });

    test('opening bulk update parent modal pre-fills current parentId', function () {
        $user = User::factory()->withTeam()->create();
        $parent = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Bedroom']);
        Item::factory()->for($user->currentTeam)->childOf($parent)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('parentId', $parent->id)
            ->call('openBulkUpdateParentModal')
            ->assertSet('bulkParentId', $parent->id);
    });

    test('opening bulk update parent modal sets null when at root level', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('openBulkUpdateParentModal')
            ->assertSet('bulkParentId', null);
    });

    test('bulk update requires at least one selected item', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('selected', [])
            ->call('updateParent')
            ->assertHasErrors('selected');
    });

    test('ignores items from another team', function () {
        $user = User::factory()->withTeam()->create();
        $otherItem = Item::factory()->create(['parent_id' => null]);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('selected', [$otherItem->id])
            ->call('updateParent')
            ->assertHasNoErrors();

        expect($otherItem->fresh()->parent_id)->toBeNull();
    });
});

describe('bulk delete', function () {
    test('can bulk delete selected items', function () {
        $user = User::factory()->withTeam()->create();
        [$itemA, $itemB, $itemC] = Item::factory()->count(3)->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('selected', [$itemA->id, $itemC->id])
            ->call('bulkDelete')
            ->assertHasNoErrors()
            ->assertSet('selected', []);

        expect($itemA->fresh())->toBeTrashed()
            ->and($itemB->fresh())->not->toBeTrashed()
            ->and($itemC->fresh())->toBeTrashed();
    });

    test('bulk delete does nothing when no items are selected', function () {
        $user = User::factory()->withTeam()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('bulkDelete')
            ->assertHasNoErrors();
    });

    test('can bulk restore selected trashed items', function () {
        $user = User::factory()->withTeam()->create();
        [$itemA, $itemB, $itemC] = Item::factory()->count(3)->for($user->currentTeam)->trashed()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('selected', [$itemA->id, $itemC->id])
            ->call('bulkRestore')
            ->assertHasNoErrors()
            ->assertSet('selected', []);

        expect($itemA->fresh())->not->toBeTrashed()
            ->and($itemB->fresh())->toBeTrashed()
            ->and($itemC->fresh())->not->toBeTrashed();
    });
});

describe('can generate qr codes', function () {
    test('can show qr code for an item', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('showQrCode', $item->id)
            ->assertSet('qrCode.name', 'Guitar')
            ->assertNotSet('qrCode.svg', '');
    });

    test('qr code svg contains valid svg markup', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create();

        $component = Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('showQrCode', $item->id);

        expect($component->get('qrCode.svg'))->toContain('<svg');
    });

    test('cannot show qr code for an item from another team', function () {
        $user = User::factory()->withTeam()->create();
        $otherItem = Item::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('showQrCode', $otherItem->id);
    })->throws(ModelNotFoundException::class);
});

describe('move to team feature', function () {
    test('can move item to another team', function () {
        $sunny = Team::factory()->create(['name' => 'Sunny']);
        $user = User::factory()->withTeam('Merry')->hasAttached($sunny)->create();

        $item = Item::factory()->for($user->currentTeam)->create([
            'name' => 'Tangerines',
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('openMoveModal', $item->id)
            ->assertSet('moveItemId', $item->id)
            ->set('moveToTeamId', $sunny->id)
            ->call('moveToTeam');

        expect($item->fresh())
            ->team_id->toBe($sunny->id)
            ->parent_id->toBeNull();

        expect($user->currentTeam->items()->where('name', 'Tangerines')->exists())->toBeFalse();
    });

    test('cannot move item to a team user does not belong to', function () {
        $user = User::factory()->withTeam()->create();
        $otherTeam = Team::factory()->create();

        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('openMoveModal', $item->id)
            ->set('moveToTeamId', $otherTeam->id)
            ->call('moveToTeam')
            ->assertHasErrors('moveToTeamId');
    });
});

describe('can filter', function () {
    test('type filter only shows items by type', function () {
        $user = User::factory()->withTeam()->create();

        $location = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Merry']);
        Item::factory()->for($user->currentTeam)->childOf($location)->item()->create(['name' => 'Tangerines']);
        Item::factory()->for($user->currentTeam)->item()->create(['name' => 'Golden Bell']);
        Item::factory()->for($user->currentTeam)->bin()->create(['name' => 'Treasure Chest']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->set('filters.types', [ItemType::Item])
            ->assertSeeHtml('<span>Golden Bell</span>')
            ->assertDontSeeHtml('<span>Treasure Chest</span>')
            ->assertDontSeeHtml('<span>Merry</span>')
            ->assertDontSeeHtml('<span>Tangerines</span>')
            ->set('filters.types', [ItemType::Bin])
            ->assertDontSeeHtml('<span>Golden Bell</span>')
            ->assertSeeHtml('<span>Treasure Chest</span>')
            ->assertDontSeeHtml('<span>Merry</span>')
            ->assertDontSeeHtml('<span>Tangerines</span>')
            ->set('filters.types', [ItemType::Location])
            ->assertDontSeeHtml('<span>Golden Bell</span>')
            ->assertDontSeeHtml('<span>Treasure Chest</span>')
            ->assertSeeHtml('<span>Merry</span>')
            ->assertDontSeeHtml('<span>Tangerines</span>');
    });

    test('type filter works when navigated into a child', function () {
        $user = User::factory()->withTeam()->create();

        $location = Item::factory()->location()->for($user->currentTeam)->create(['name' => 'Merry']);
        Item::factory()->for($user->currentTeam)->childOf($location)->item()->create(['name' => 'Tangerines']);
        Item::factory()->for($user->currentTeam)->childOf($location)->bin()->create(['name' => 'Small Box']);

        Livewire::actingAs($user)
            ->test('pages::inventory.index')
            ->call('navigateDown', $location->id)
            ->assertSeeHtml('<span>Tangerines</span>')
            ->assertSeeHtml('<span>Small Box</span>')
            ->set('filters.types', [ItemType::Item])
            ->assertSeeHtml('<span>Tangerines</span>')
            ->assertDontSeeHtml('<span>Small Box</span>')
            ->set('filters.types', [ItemType::Bin])
            ->assertDontSeeHtml('<span>Tangerines</span>')
            ->assertSeeHtml('<span>Small Box</span>');
    });
});
