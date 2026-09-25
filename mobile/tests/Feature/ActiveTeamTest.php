<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Team;
use App\NativeComponents\Inventory;
use App\NativeComponents\Recipes;
use Native\Mobile\Testing\Native;
use Saloon\Config;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    config(['services.sunny.api_url' => 'https://sunny.example/api']);
    Config::preventStrayRequests();
    seedSunnyData();
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Work', 'slug' => 'work']);
    Recipe::create([...Recipe::find(1)->toArray(), 'id' => 90, 'team_id' => 2, 'name' => 'Work lunch']);
    Item::create([...Item::find(1)->toArray(), 'id' => 90, 'team_id' => 2, 'parent_id' => null, 'name' => 'Office']);
});

it('switches browsing and recent activity locally and remembers the team', function (): void {
    Saloon::fake([]);
    Native::visit('/dashboard')->assertSee('Buttermilk Pancakes')
        ->tap('active-team')->assertSet('showTeamPicker', true)
        ->tap('team-2')->assertSet('showTeamPicker', false)->assertSee('Work lunch')->assertSee('Office')->assertDontSee('Buttermilk Pancakes');
    Native::visit('/recipes')->assertSee('Work lunch')->assertDontSee('Buttermilk Pancakes');
    Native::visit('/inventory')->assertSee('Office')->assertDontSee('Kitchen');
    Native::visit('/dashboard')->assertSet('activeTeamName', 'Work');
    expect((new SunnyTeam)->current()->id)->toBe(2);
    expect(Recipes::find(1))->toBeNull()->and(Inventory::find(1))->toBeNull();
    Native::visit('/recipes/1/edit')->assertSee('This recipe could not be found.');
    Native::visit('/inventory/1')->assertSee('This item could not be found.');
    Saloon::assertNothingSent();
});

it('only opens the team picker when there is another team to switch to', function (): void {
    Team::find(2)->delete();
    Native::visit('/dashboard')->tap('active-team')->assertSet('showTeamPicker', false);
});

it('creates records in the active team without a form picker', function (string $route, string $ref, string $model): void {
    app(SunnyTeam::class)->select(2);
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'token']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $model::find(90)->toArray()], 201)]);
    Native::visit('/'.$route.'/create')->assertSet('teamId', 2)
        ->assertMissingElement('select', fn (array $node): bool => ($node['ref'] ?? null) === 'form-team')
        ->set('name', 'New record')->tap($ref.'-submit')->assertSet('error', '');
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => str_starts_with($request->resolveEndpoint(), '/teams/work/'));
})->with([['recipes', 'create-recipe', Recipe::class], ['inventory', 'create-item', Item::class]]);

it('preserves selection through sync and falls back when membership disappears', function (): void {
    app(SunnyTeam::class)->select(2);
    $snapshot = ['teams' => Team::all()->map->only(['id', 'name', 'slug'])->all(), 'recipes' => [], 'items' => [], 'synced_at' => now()->toIso8601String()];
    app(SunnyStore::class)->applySnapshot($snapshot);
    expect(app(SunnyTeam::class)->current()->id)->toBe(2);
    $snapshot['teams'] = [$snapshot['teams'][0]];
    app(SunnyStore::class)->applySnapshot($snapshot);
    expect(app(SunnyTeam::class)->current()->id)->toBe(1);
    app(SunnyStore::class)->clear();
    expect(app(SunnyTeam::class)->current())->toBeNull();
    Native::visit('/recipes/create')->set('name', 'No team')->tap('create-recipe-submit')->assertNoNavigation();
});

it('does not silently move an open draft when the active team changes', function (): void {
    $form = Native::visit('/recipes/create')->set('name', 'Family recipe');
    app(SunnyTeam::class)->select(2);
    $form->tap('create-recipe-submit')->assertNoNavigation()
        ->assertSee('The active team changed. Reopen this form from the dashboard before saving.');
});

it('distinguishes identically named teams and rejects invalid selections', function (): void {
    Team::find(2)->update(['name' => 'Family']);
    Native::visit('/dashboard')->assertSee('Family (#2)')
        ->tap('active-team')->tap('team-2')->assertSet('activeTeamName', 'Family (#2)')
        ->call('selectTeam', 999)->assertSet('activeTeamName', 'Family (#2)');
    expect(app(SunnyTeam::class)->current()->id)->toBe(2);
});
