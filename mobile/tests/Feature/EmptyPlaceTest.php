<?php

use App\Actions\SetUpHome;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Item;
use Native\Mobile\Testing\Native;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'test-token']);
    app(SunnyStore::class)->applySnapshot([
        'teams' => [['id' => 1, 'name' => 'Alex’s Team', 'slug' => 'alexs-team']],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [],
        'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
        'synced_at' => now()->toIso8601String(),
    ]);
});

it('teaches an empty place with one-tap starter Storage', function (): void {
    $id = app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Garage', 'type' => ItemType::Location->value, 'parent_id' => null, 'metadata' => null]);
    $garage = Item::query()->findOrFail($id);

    Native::visit('/inventory/'.$garage->id)
        ->assertSee('Nothing stored here yet')
        ->assertSee('Tap one to add it to Garage')
        ->assertSee('Camping box')
        ->assertSee('Add to Garage')
        ->assertSee('Where it is')
        ->assertDontSee('What’s inside')
        ->assertAccessible()
        ->tap('item-starter-1')
        ->assertDontSee('Nothing stored here yet')
        ->assertSee('What’s inside (1)')
        ->assertSee('Tool chest');

    expect(Item::query()->where('name', 'Tool chest')->sole())
        ->type->toBe(ItemType::Bin)
        ->parent_id->toBe($garage->id);
});

it('offers general Storage for a place it doesn’t know', function (): void {
    $id = app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Sunroom', 'type' => ItemType::Location->value, 'parent_id' => null, 'metadata' => null]);

    Native::visit('/inventory/'.$id)->assertSet('starterStorage', ['Top shelf', 'Storage box', 'Drawer', 'Cabinet']);
});

it('doesn’t teach Storage or things that are empty', function (): void {
    $id = app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Toolbox', 'type' => ItemType::Bin->value, 'parent_id' => null, 'metadata' => null]);

    Native::visit('/inventory/'.$id)->assertSet('starterStorage', [])->assertSee('What’s inside');
});

it('offers the same Storage as the website for each starter place', function (): void {
    $id = app(SunnyOutbox::class)->queue('items', 1, ['name' => SetUpHome::PLACES[7], 'type' => ItemType::Location->value, 'parent_id' => null, 'metadata' => null]);

    Native::visit('/inventory/'.$id)->assertSet('starterStorage', ['Toy box', 'Bookshelf', 'Art supplies box', 'Dresser']);
});
