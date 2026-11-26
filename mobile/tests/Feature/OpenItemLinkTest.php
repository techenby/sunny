<?php

use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\Team;
use App\NativeComponents\OpenItemLink;
use Native\Mobile\Facades\Browser;
use Native\Mobile\Testing\Native;

it('opens a linked item', function () {
    seedSunnyData();

    Native::visit('/i/8')->assertReplacedWith('/inventory/8');
});

it('switches to the linked item’s team before opening it', function () {
    seedSunnyData();
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Work', 'slug' => 'work']);
    Item::create([...Item::find(1)->toArray(), 'id' => 90, 'team_id' => 2, 'parent_id' => null, 'name' => 'Office']);

    Native::visit('/i/90')->assertReplacedWith('/inventory/90');

    expect(app(SunnyTeam::class)->current()->id)->toBe(2);
});

it('sends a link to the welcome screen before anything has synced', function () {
    Native::visit('/i/8')->assertReplacedStackWith('/');
});

it('offers the browser when the linked item is not on this phone', function () {
    seedSunnyData();
    Browser::shouldReceive('open')->once()->with('https://sunny.example/i/999')->andReturn(true);

    Native::visit('/i/999')
        ->assertScreen(OpenItemLink::class)
        ->assertSee('This thing isn’t on this phone. It may be in a team you’re not part of, or it’s new. Pull down on Summary to refresh.')
        ->tap('item-link-open-browser')
        ->assertNoNavigation()
        ->assertSet('error', '');
});

it('says a linked item was deleted, where it was, and opens Recently deleted', function () {
    seedSunnyData();
    $item = Item::find(8);
    $place = Item::find($item->parent_id);
    $item->delete();

    Native::visit('/i/8')
        ->assertScreen(OpenItemLink::class)
        ->assertSee($item->name.' was deleted')
        ->assertSee('It’s waiting in Recently deleted. You can restore it there, or delete it forever.')
        ->assertSee('Where it was')
        ->assertSee($place->name)
        ->assertDontSee('This thing isn’t on this phone')
        ->assertAccessible()
        ->tap('item-link-open-deleted')
        ->assertReplacedWith('/inventory/deleted');
});

it('says a deleted linked item was not in a place', function () {
    seedSunnyData();
    $item = Item::find(8);
    $item->update(['parent_id' => null]);
    $item->delete();

    Native::visit('/i/8')
        ->assertSee($item->name.' was deleted')
        ->assertSee('Not in a place yet');
});

it('switches to a deleted linked item’s team before opening Recently deleted', function () {
    seedSunnyData();
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Work', 'slug' => 'work']);
    Item::create([...Item::find(1)->toArray(), 'id' => 90, 'team_id' => 2, 'parent_id' => null, 'name' => 'Office'])->delete();

    Native::visit('/i/90')
        ->assertSee('Office was deleted')
        ->tap('item-link-open-deleted')
        ->assertReplacedWith('/inventory/deleted');

    expect(app(SunnyTeam::class)->current()->id)->toBe(2);
});

it('opens a linked item that a sync restored before Open Recently deleted was tapped', function () {
    seedSunnyData();
    Item::find(8)->delete();
    $screen = Native::visit('/i/8')->assertSee('was deleted');

    Item::onlyTrashed()->find(8)->restore();

    $screen->tap('item-link-open-deleted')
        ->assertReplacedWith('/inventory/8');
});

it('says a linked item is not on this phone when a sync removed it before the tap', function () {
    seedSunnyData();
    Item::find(8)->delete();
    $screen = Native::visit('/i/8')->assertSee('was deleted');

    Item::onlyTrashed()->find(8)->forceDelete();

    $screen->tap('item-link-open-deleted')
        ->assertNoNavigation()
        ->assertSet('deleted', null)
        ->assertSee('This thing isn’t on this phone');
});

it('says a linked item is not on this phone when its team left the phone before the tap', function () {
    seedSunnyData();
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Work', 'slug' => 'work']);
    Item::create([...Item::find(1)->toArray(), 'id' => 90, 'team_id' => 2, 'parent_id' => null, 'name' => 'Office'])->delete();
    $screen = Native::visit('/i/90')->assertSee('Office was deleted');

    Team::query()->whereKey(2)->update(['server' => 'https://elsewhere.example']);
    expect(Item::onlyTrashed()->forCurrentServer()->find(90))->not->toBeNull();

    $screen->tap('item-link-open-deleted')
        ->assertNoNavigation()
        ->assertSee('This thing isn’t on this phone');

    expect(app(SunnyTeam::class)->current()->id)->toBe(1);
});
