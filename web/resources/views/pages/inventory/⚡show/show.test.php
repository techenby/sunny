<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $item = Item::factory()->create();
    $this->get('/any-team/inventory/' . $item->id)
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the items page', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('inventory.show', ['item' => $item]))
        ->assertOk();
});

test('can view a soft deleted item', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $item->delete();

    $this->actingAs($user)
        ->get(route('inventory.show', ['item' => $item]))
        ->assertOk();
});

test('cannot view item for different team', function () {
    $user = User::factory()->create();
    $item = Item::factory()->create(['name' => 'Pink Hammer']);

    $this->actingAs($user)
        ->get(route('inventory.show', ['item' => $item]))
        ->assertForbidden();
});

test('shows the kind of thing and the trail of places it is inside', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Garage']);
    $shelf = Item::factory()->for($user->currentTeam)->bin()->childOf($garage)->create(['name' => 'Metal Shelf']);
    $bin = Item::factory()->for($user->currentTeam)->bin()->childOf($shelf)->create(['name' => 'Blue Bin']);

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $bin])
        ->assertSeeInOrder(['Blue Bin', 'Storage', 'Where it is', 'Garage', 'Metal Shelf', 'Blue Bin', 'You’re here'])
        ->assertSeeHtml('href="' . route('inventory.show', ['item' => $shelf]) . '"');
});

test('the breadcrumb shortens to the parent and keeps the full path in Where it is', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Garage']);
    $shelf = Item::factory()->for($user->currentTeam)->bin()->childOf($garage)->create(['name' => 'Metal Shelf']);
    $bin = Item::factory()->for($user->currentTeam)->bin()->childOf($shelf)->create(['name' => 'Blue Bin']);

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $bin])
        ->assertSeeInOrder(['Inventory', 'Metal Shelf', 'Blue Bin', 'Where it is', 'Garage', 'Metal Shelf'])
        ->assertSeeHtml('href="' . route('inventory.index', ['parentId' => $shelf->id]) . '"')
        ->assertDontSeeHtml('href="' . route('inventory.index', ['parentId' => $garage->id]) . '"');
});

test('says when something is not in a place yet', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item])
        ->assertSee('Not in a place yet');
});

test('shows a top level place as part of the home', function () {
    $user = User::factory()->create();
    $garage = Item::factory()->for($user->currentTeam)->location()->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $garage])
        ->assertSee('One of the places in your home')
        ->assertDontSee('Choose Move to put it away.');
});

describe('move', function () {
    test('can move an item into another place', function () {
        $user = User::factory()->create();
        $garage = Item::factory()->for($user->currentTeam)->location()->create(['name' => 'Garage']);
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Hammer']);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('startMove')
            ->assertSet('moveParentId', null)
            ->set('moveParentId', $garage->id)
            ->call('move')
            ->assertHasNoErrors()
            ->assertSeeInOrder(['Where it is', 'Garage']);

        expect($item->fresh()->parent_id)->toBe($garage->id);
    });

    test('can take an item out of its place', function () {
        $user = User::factory()->create();
        $garage = Item::factory()->for($user->currentTeam)->location()->create();
        $item = Item::factory()->for($user->currentTeam)->childOf($garage)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('startMove')
            ->assertSet('moveParentId', $garage->id)
            ->set('moveParentId', null)
            ->call('move')
            ->assertHasNoErrors()
            ->assertSee('Not in a place yet');

        expect($item->fresh()->parent_id)->toBeNull();
    });

    test('cannot move an item inside itself or something it contains', function () {
        $user = User::factory()->create();
        $bin = Item::factory()->for($user->currentTeam)->bin()->create();
        $child = Item::factory()->for($user->currentTeam)->bin()->childOf($bin)->create();
        $grandchild = Item::factory()->for($user->currentTeam)->childOf($child)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $bin])
            ->set('moveParentId', $bin->id)
            ->call('move')
            ->assertHasErrors('moveParentId')
            ->set('moveParentId', $grandchild->id)
            ->call('move')
            ->assertHasErrors('moveParentId');

        expect($bin->fresh()->parent_id)->toBeNull();
    });

    test('cannot move an item into another team’s place', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();
        $elsewhere = Item::factory()->location()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->set('moveParentId', $elsewhere->id)
            ->call('move')
            ->assertHasErrors('moveParentId');

        expect($item->fresh()->parent_id)->toBeNull();
    });
});

test('the delete confirmation names the item and says where its contents go', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->for($user->currentTeam)->bin()->create(['name' => 'Tool Chest']);
    Item::factory()->for($user->currentTeam)->childOf($bin)->count(2)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $bin])
        ->assertSee('“Tool Chest” will be deleted for everyone on your team. The 2 things inside it will move to “Not in a place yet”.');
});

test('a deleted item does not offer delete again', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item])
        ->assertSeeHtml('wire:click="delete"');

    $item->delete();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item->fresh()])
        ->assertDontSeeHtml('wire:click="delete"');
});

describe('contents', function () {
    test('lists the items inside', function () {
        $user = User::factory()->create();
        $bin = Item::factory()->for($user->currentTeam)->bin()->create(['name' => 'Blue Bin']);
        Item::factory()->for($user->currentTeam)->childOf($bin)->create(['name' => 'Extension Cord']);
        Item::factory()->for($user->currentTeam)->create(['name' => 'Garden Hose']);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $bin])
            ->assertSee('What’s inside (1)')
            ->assertSeeHtml('<span>Extension Cord</span>')
            ->assertDontSeeHtml('<span>Garden Hose</span>');
    });

    test('says when nothing is inside', function () {
        $user = User::factory()->create();
        $bin = Item::factory()->for($user->currentTeam)->bin()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $bin])
            ->assertSee('Nothing inside yet.');
    });

    test('a thing with nothing inside offers a quiet way to put something inside', function () {
        $user = User::factory()->create();
        $thing = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $thing])
            ->assertSee('Does this hold other things?')
            ->assertDontSee('Nothing inside yet.')
            ->assertDontSee('What’s inside')
            ->call('create')
            ->assertSet('form.parent_id', $thing->id);
    });

    test('a thing with something inside lists it', function () {
        $user = User::factory()->create();
        $thing = Item::factory()->for($user->currentTeam)->create();
        Item::factory()->for($user->currentTeam)->childOf($thing)->create(['name' => 'Spare Strings']);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $thing])
            ->assertSee('What’s inside (1)')
            ->assertSeeHtml('<span>Spare Strings</span>')
            ->assertDontSee('Does this hold other things?');
    });

    test('can add an item inside', function () {
        $user = User::factory()->create();
        $bin = Item::factory()->for($user->currentTeam)->bin()->create(['name' => 'Blue Bin']);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $bin])
            ->call('create')
            ->assertSet('form.parent_id', $bin->id)
            ->assertSet('form.type', ItemType::Item->value)
            ->set('form.name', 'Extension Cord')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSeeHtml('<span>Extension Cord</span>');

        expect($bin->children()->sole())
            ->name->toBe('Extension Cord')
            ->type->toBe(ItemType::Item)
            ->team_id->toBe($user->current_team_id);
    });

    test('offers the item itself as a parent when adding', function () {
        $user = User::factory()->create();
        $bin = Item::factory()->for($user->currentTeam)->bin()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $bin])
            ->call('create')
            ->assertSet('parentItems', fn ($items) => $items->contains($bin));
    });

    test('cannot add items inside a deleted item', function () {
        $user = User::factory()->create();
        $bin = Item::factory()->for($user->currentTeam)->bin()->create();
        $bin->delete();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $bin])
            ->assertDontSeeHtml('wire:click="create"');
    });
});

test('can edit an item', function () {
    $user = User::factory()->create();
    $bin = Item::factory()->for($user->currentTeam)->create(['name' => 'Soft Shell Case', 'type' => ItemType::Bin]);
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item])
        ->call('edit')
        ->assertSet('form.name', 'Guitar')
        ->assertSet('form.parent_id', null)
        ->set('form.name', 'Yamaha Guitar')
        ->set('form.parent_id', $bin->id)
        ->call('save')
        ->assertHasNoErrors()
        ->assertSet('form.name', '')
        ->assertSet('form.parent_id', null)
        ->assertSee('Yamaha Guitar')
        ->assertSeeInOrder(['Where it is', 'Soft Shell Case']);

    expect($item->fresh())
        ->name->toBe('Yamaha Guitar')
        ->parent_id->toBe($bin->id);
});

test('can duplicate an item multiple times', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item])
        ->set('duplicateCount', 2)
        ->call('duplicate')
        ->assertHasNoErrors()
        ->assertSet('duplicateCount', 1);

    expect($user->currentTeam->items()->where('name', 'Guitar')->count())->toBe(3);
});

test('duplicate count must be between 1 and 25', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item])
        ->set('duplicateCount', 0)
        ->call('duplicate')
        ->assertHasErrors(['duplicateCount' => 'min'])
        ->set('duplicateCount', 26)
        ->call('duplicate')
        ->assertHasErrors(['duplicateCount' => 'max']);

    expect(Item::count())->toBe(1);
});

test('can delete an item and redirects to index', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item])
        ->call('delete')
        ->assertRedirect(route('inventory.index'));

    expect($item->fresh()->trashed())->toBeTrue();
});

test('deleting a parent nullifies children parent_id', function () {
    $user = User::factory()->create();
    $parent = Item::factory()->for($user->currentTeam)->location()->create();
    $child = Item::factory()->for($user->currentTeam)->childOf($parent)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $parent])
        ->call('delete', $parent->id);

    expect($child->fresh()->parent_id)->toBeNull();
});

describe('can add item metadata', function () {
    test('can add a metadata field', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create([
            'metadata' => ['color' => 'red'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
            ->call('addMetadata')
            ->assertSet('form.metadata', [
                ['key' => 'color', 'value' => 'red'],
                ['key' => '', 'value' => ''],
            ]);
    });

    test('can update an item with metadata', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create([
            'metadata' => ['color' => 'red'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
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
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create([
            'metadata' => ['color' => 'red'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
            ->set('form.metadata', [
                ['key' => '', 'value' => 'orphan'],
                ['key' => 'valid', 'value' => 'kept'],
            ])
            ->call('save');

        expect($item->fresh()->metadata)->toBe(['valid' => 'kept']);
    });

    test('item with no metadata stores null', function () {
        $user = User::factory()->create();

        $item = Item::factory()->for($user->currentTeam)->create([
            'metadata' => ['color' => 'red'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
            ->call('removeMetadata', 0)
            ->call('save');

        expect($item->fresh()->metadata)->toBeNull();
    });

    test('metadata value is required when key is present', function () {
        $user = User::factory()->create();

        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
            ->set('form.metadata', [
                ['key' => 'color', 'value' => ''],
            ])
            ->call('save')
            ->assertHasErrors('form.metadata.0.value');
    });

    test('duplicate metadata keys are rejected', function () {
        $user = User::factory()->create();

        $item = Item::factory()->for($user->currentTeam)->create([
            'metadata' => ['color' => 'red'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
            ->set('form.metadata', [
                ['key' => 'color', 'value' => 'red'],
                ['key' => 'color', 'value' => 'blue'],
            ])
            ->call('save')
            ->assertHasErrors('form.metadata.1.key');
    });

    test('editing an item loads existing metadata', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create([
            'metadata' => ['url' => 'https://example.com'],
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
            ->assertSet('form.metadata', [
                ['key' => 'url', 'value' => 'https://example.com'],
            ]);
    });
});

describe('can manage item photos', function () {
    test('shows every photo with the primary one first', function () {
        Storage::fake();
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);
        ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/back.jpg', 'position' => 1]);
        ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/front.jpg', 'position' => 0]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->assertSeeInOrder(['front.jpg', 'back.jpg'])
            ->assertSee('1 of 2')
            ->assertSee('Primary')
            ->assertSee('Make primary')
            ->assertSee('Add photo');
    });

    test('offers to add a photo when there are none', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->assertSee('Add a photo')
            ->assertDontSee('Make primary');
    });

    test('can add several photos at once', function () {
        Storage::fake();
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->withPhoto('teams/1/items/front.jpg')->create(['name' => 'Guitar']);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->set('newPhotos', [UploadedFile::fake()->image('back.jpg'), UploadedFile::fake()->image('scratch.png')])
            ->assertHasNoErrors()
            ->assertSet('newPhotos', []);

        $photos = $item->photos()->get();

        expect($photos)->toHaveCount(3)
            ->and($photos->pluck('position')->all())->toBe([0, 1, 2])
            ->and($photos->first()->path)->toBe('teams/1/items/front.jpg');
        Storage::assertExists($photos->last()->path);
        Storage::assertExists($photos->last()->thumb_path);
    });

    test('stops at ten photos', function () {
        Storage::fake();
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();
        ItemPhoto::factory()->for($item)->count(9)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->set('newPhotos', [UploadedFile::fake()->image('one.jpg'), UploadedFile::fake()->image('two.jpg')])
            ->assertHasErrors(['newPhotos' => 'An item can have up to 10 photos.']);

        expect($item->photos()->count())->toBe(9);
    });

    test('rejects files that are not images', function () {
        Storage::fake();
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->set('newPhotos', [UploadedFile::fake()->create('manual.pdf', 100, 'application/pdf')])
            ->assertHasErrors('newPhotos.0');

        expect($item->photos()->count())->toBe(0);
    });

    test('can make a photo the primary one', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();
        [$front, $back, $scratch] = ItemPhoto::factory()->for($item)->count(3)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('makePrimary', $scratch->id);

        expect($item->photos()->pluck('id')->all())->toBe([$scratch->id, $front->id, $back->id])
            ->and($item->fresh()->primaryPhoto->id)->toBe($scratch->id);
    });

    test('can drag a photo to a new position', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();
        [$front, $back, $scratch] = ItemPhoto::factory()->for($item)->count(3)->sequence(fn ($sequence) => ['position' => $sequence->index])->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('movePhoto', $front->id, 2);

        expect($item->photos()->pluck('id')->all())->toBe([$back->id, $scratch->id, $front->id]);
    });

    test('can delete a photo', function () {
        Storage::fake();
        Storage::put('teams/1/items/front.jpg', 'front');
        Storage::put('teams/1/items/thumbs/front.jpg', 'thumb');
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();
        $front = ItemPhoto::factory()->for($item)->create(['path' => 'teams/1/items/front.jpg', 'thumb_path' => 'teams/1/items/thumbs/front.jpg', 'position' => 0]);
        $back = ItemPhoto::factory()->for($item)->create(['position' => 1]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('deletePhoto', $front->id);

        expect($item->photos()->get())->toHaveCount(1)
            ->first()->id->toBe($back->id)
            ->first()->position->toBe(0);
        Storage::assertMissing('teams/1/items/front.jpg');
        Storage::assertMissing('teams/1/items/thumbs/front.jpg');
    });

    test('cannot touch another item\'s photos', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();
        $other = ItemPhoto::factory()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('deletePhoto', $other->id)
            ->assertNotFound();

        expect($other->fresh())->not->toBeNull();
    });

    test('hides photo controls on a deleted item', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->withPhoto('teams/1/items/front.jpg')->create();
        ItemPhoto::factory()->for($item)->create(['position' => 1]);
        $item->delete();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->assertSee('front.jpg')
            ->assertDontSee('Make primary')
            ->assertDontSee('Add photo');
    });

    test('putting something inside can give it a first photo', function () {
        Storage::fake();
        $user = User::factory()->create();
        $bin = Item::factory()->for($user->currentTeam)->bin()->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $bin])
            ->call('create')
            ->set('form.name', 'Guitar')
            ->set('form.photo', UploadedFile::fake()->image('guitar.jpg'))
            ->call('save')
            ->assertHasNoErrors();

        expect(Item::firstWhere('name', 'Guitar')->photos)->toHaveCount(1);
    });

    test('photo validation rejects non-image files', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('create')
            ->set('form.name', 'Manual')
            ->set('form.photo', $file)
            ->call('save')
            ->assertHasErrors('form.photo');
    });
});

describe('can generate qr codes', function () {
    test('can show qr code for an item', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('showQrCode')
            ->assertSet('qrCode.name', 'Guitar')
            ->assertNotSet('qrCode.svg', '');
    });

    test('qr code svg contains valid svg markup', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();

        $component = Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('showQrCode');

        expect($component->get('qrCode.svg'))->toContain('<svg');
    });
});

describe('move to team feature', function () {
    test('can move item to another team', function () {
        [$merry, $sunny] = Team::factory()->count(2)->sequence(['name' => 'Merry'], ['name' => 'Sunny'])->create();
        $user = User::factory()->hasAttached([$merry, $sunny])->create();

        $item = Item::factory()->for($user->currentTeam)->create([
            'name' => 'Tangerines',
        ]);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->set('moveToTeamId', $sunny->id)
            ->call('moveToTeam')
            ->assertRedirect(route('inventory.index'));

        expect($item->fresh())
            ->team_id->toBe($sunny->id)
            ->parent_id->toBeNull();
    });

    test('cannot move item to a team user does not belong to', function () {
        $user = User::factory()->create();
        $otherTeam = Team::factory()->create();

        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->set('moveToTeamId', $otherTeam->id)
            ->call('moveToTeam')
            ->assertHasErrors('moveToTeamId');
    });

    test('cannot move item to the team it is already in', function () {
        $user = User::factory()->create();
        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->set('moveToTeamId', $item->team_id)
            ->call('moveToTeam')
            ->assertHasErrors(['moveToTeamId' => 'not_in'])
            ->assertSee('The selected team is invalid.');

        expect($item->fresh()->team_id)->toBe($user->current_team_id);
    });
});

test('shows and edits details saved before they had to be text', function () {
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create([
        'name' => 'Tent',
        'metadata' => ['size' => ['w' => 2, 'h' => 3], 'count' => 4],
    ]);

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item])
        ->assertSee('{"w":2,"h":3}')
        ->assertSee('Count')
        ->call('edit')
        ->assertSet('form.metadata', [
            ['key' => 'size', 'value' => '{"w":2,"h":3}'],
            ['key' => 'count', 'value' => '4'],
        ]);
});
