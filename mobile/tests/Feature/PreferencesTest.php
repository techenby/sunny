<?php

use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Recipe;
use App\Models\Team;
use App\NativeComponents\Home;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;

beforeEach(function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);
    seedSunnyData();
});

it('lists the teams with a check on the active one', function () {
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Work', 'slug' => 'work']);

    Native::visit('/preferences', platform: 'ios')
        ->assertNavTitle('Preferences')
        ->assertSee('Family')
        ->assertSee('Work')
        ->assertSet('activeTeamId', 1)
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'team-1' && ($node['props']['trailing_icon'] ?? null) === 'checkmark')
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'team-2' && ! isset($node['props']['trailing_icon']))
        ->assertElement('list_item', fn (array $node): bool => ($node['ref'] ?? null) === 'log-out' && ($node['props']['leading_icon'] ?? null) === 'rectangle.portrait.and.arrow.right')
        ->assertAccessible();
});

it('switches team and refreshes the summary on the way back', function () {
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Work', 'slug' => 'work']);
    Recipe::create([...Recipe::find(1)->toArray(), 'id' => 90, 'team_id' => 2, 'name' => 'Work lunch']);

    Native::visit('/dashboard')
        ->assertSee('Buttermilk Pancakes')
        ->tap('preferences')
        ->follow()
        ->tap('team-2')
        ->assertSet('activeTeamId', 2)
        ->goBack()
        ->assertSee('Work lunch')
        ->assertDontSee('Buttermilk Pancakes');

    expect(app(SunnyTeam::class)->current()->id)->toBe(2);
});

it('explains when there are no teams yet', function () {
    Team::query()->delete();

    Native::visit('/preferences')
        ->assertSee('No teams available')
        ->assertElement('list_section', fn (array $node): bool => ($node['props']['footer'] ?? null) === 'Pull down on Summary to download your teams.');
});

it('asks for confirmation before logging out', function () {
    Native::visit('/preferences')
        ->tap('log-out')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'log-out')
        ->assertNoNavigation();
});

it('returns to the home screen when logging out is confirmed', function () {
    Native::visit('/preferences')
        ->tap('log-out')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Log out', 'id' => 'log-out'])
        ->assertReplacedWith('/')
        ->follow()
        ->assertScreen(Home::class);
});

it('stays on preferences when logging out is cancelled', function () {
    Native::visit('/preferences')
        ->tap('log-out')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'log-out'])
        ->assertNoNavigation();
});
