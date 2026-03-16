<?php

use App\Enums\ItemType;
use App\Models\Item;
use App\Models\Team;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $item = Item::factory()->create();
    $this->get(route('inventory.show', ['item' => $item]))
        ->assertRedirect(route('login'));
});

test('authenticated users can visit the items page', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    $this->actingAs($user)
        ->get(route('inventory.show', ['item' => $item]))
        ->assertOk();
});

test('can view a soft deleted item', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $item->delete();

    $this->actingAs($user)
        ->get(route('inventory.show', ['item' => $item]))
        ->assertOk();
});

test('cannot view item for different team', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->create(['name' => 'Pink Hammer']);

    $this->actingAs($user)
        ->get(route('inventory.show', ['item' => $item]))
        ->assertForbidden();
});

test('can edit an item', function () {
    $user = User::factory()->withTeam()->create();
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
        ->assertSet('form.parent_id', null);

    expect($item->fresh())
        ->name->toBe('Yamaha Guitar')
        ->parent_id->toBe($bin->id);
});

test('can delete an item and redirects to index', function () {
    $user = User::factory()->withTeam()->create();
    $item = Item::factory()->for($user->currentTeam)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $item])
        ->call('delete')
        ->assertRedirect(route('inventory.index'));

    expect($item->fresh()->trashed())->toBeTrue();
});

test('deleting a parent nullifies children parent_id', function () {
    $user = User::factory()->withTeam()->create();
    $parent = Item::factory()->for($user->currentTeam)->location()->create();
    $child = Item::factory()->for($user->currentTeam)->childOf($parent)->create();

    Livewire::actingAs($user)
        ->test('pages::inventory.show', ['item' => $parent])
        ->call('delete', $parent->id);

    expect($child->fresh()->parent_id)->toBeNull();
});

describe('can add item metadata', function () {
    test('can update an item with metadata', function () {
        $user = User::factory()->withTeam()->create();
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
        $user = User::factory()->withTeam()->create();
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
        $user = User::factory()->withTeam()->create();

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
        $user = User::factory()->withTeam()->create();

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
        $user = User::factory()->withTeam()->create();

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
        $user = User::factory()->withTeam()->create();
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
    test('can update an item with a new photo', function () {
        Storage::fake();
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar', 'photo_path' => 'teams/1/items/guitar.jpg']);
        Storage::put('teams/1/items/guitar.jpg', 'old');

        $newPhoto = UploadedFile::fake()->image('new-guitar.png');

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
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
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
            ->call('removePhoto')
            ->call('save')
            ->assertHasNoErrors();

        expect($item->fresh()->photo_path)->toBeNull();
        Storage::assertMissing('teams/1/items/guitar.jpg');
    });

    test('photo validation rejects non-image files', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);
        $file = UploadedFile::fake()->create('document.pdf', 100, 'application/pdf');

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('edit')
            ->set('form.photo', $file)
            ->call('save')
            ->assertHasErrors('form.photo');
    });
});

describe('can generate qr codes', function () {
    test('can show qr code for an item', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create(['name' => 'Guitar']);

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('showQrCode')
            ->assertSet('qrCode.name', 'Guitar')
            ->assertNotSet('qrCode.svg', '');
    });

    test('qr code svg contains valid svg markup', function () {
        $user = User::factory()->withTeam()->create();
        $item = Item::factory()->for($user->currentTeam)->create();

        $component = Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->call('showQrCode');

        expect($component->get('qrCode.svg'))->toContain('<svg');
    });
});

describe('move to team feature', function () {
    test('can move item to another team', function () {
        $sunny = Team::factory()->create();
        $user = User::factory()->withTeam('Merry')->hasAttached($sunny)->create();

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
        $user = User::factory()->withTeam()->create();
        $otherTeam = Team::factory()->create();

        $item = Item::factory()->for($user->currentTeam)->create();

        Livewire::actingAs($user)
            ->test('pages::inventory.show', ['item' => $item])
            ->set('moveToTeamId', $otherTeam->id)
            ->call('moveToTeam')
            ->assertHasErrors('moveToTeamId');
    });
});
