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
    Native::visit('/i/8')->assertReplacedWith('/');
});

it('offers the browser when the linked item is not on this phone', function () {
    seedSunnyData();
    Browser::shouldReceive('open')->once()->with('https://sunny.example/i/999')->andReturn(true);

    Native::visit('/i/999')
        ->assertScreen(OpenItemLink::class)
        ->assertSee('This item isn’t on this phone. It may be in a team you’re not part of, or it hasn’t synced yet.')
        ->tap('item-link-open-browser')
        ->assertNoNavigation()
        ->assertSet('error', '');
});
