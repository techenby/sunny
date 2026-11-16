<?php

use App\Actions\SetUpHome;
use App\Enums\ItemType;
use App\Http\Integrations\Sunny\Requests\ImportRecipeRequest;
use App\Http\Integrations\Sunny\Requests\MarkOnboardedRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\Onboarding;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'test-token']);
    app(SunnyStore::class)->applySnapshot([
        'teams' => [['id' => 1, 'name' => 'Alex’s Team', 'slug' => 'alexs-team']],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [],
        'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
        'synced_at' => now()->toIso8601String(),
    ]);
    app(SunnyStore::class)->rememberAccount(42);
    Saloon::fake([
        MarkOnboardedRequest::class => MockResponse::make(['id' => 42]),
        ImportRecipeRequest::class => MockResponse::make([], 422),
    ]);
});

function setUpStarterHome(): Onboarding
{
    $onboarding = Onboarding::begin(42);
    app(SetUpHome::class)->handle($onboarding, app(SunnyTeam::class)->current(), ['Garage', 'Hall closet'], ['groceries', 'bedtime']);

    return $onboarding->refresh();
}

it('shows nothing to people who aren’t new', function (): void {
    Native::visit('/dashboard')->assertSet('gettingStarted', null)->assertDontSee('Get started');
});

it('counts set-up as the first job and points at the first place it made', function (): void {
    setUpStarterHome();
    $garage = Item::query()->where('name', 'Garage')->sole();

    Native::visit('/dashboard')
        ->assertSee('Get started')
        ->assertSee('1 of 4 done')
        ->assertSee('Add to Garage')
        ->assertSee('Hide this checklist')
        ->assertDontSee('Undo')
        ->tap('getting-started-place')
        ->assertNavigatedTo('/inventory/'.$garage->id);
});

it('ticks jobs from what the home really has', function (): void {
    setUpStarterHome();
    $garage = Item::query()->where('name', 'Garage')->sole();
    app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Tool chest', 'type' => ItemType::Bin->value, 'parent_id' => $garage->id, 'metadata' => null]);
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Weeknight chili']);

    Native::visit('/dashboard')
        ->assertSee('3 of 4 done')
        ->assertSet('gettingStarted.jobs.3.next', true);
});

it('opens the team page on the website to invite family', function (): void {
    setUpStarterHome();
    Native::fakeBridge()->respondTo('Browser.OpenInApp', ['success' => true]);

    Native::visit('/dashboard')->tap('getting-started-invite');

    Native::fakeBridge()->assertCalled('Browser.OpenInApp', fn (array $params): bool => str_ends_with($params['url'], '/settings/teams/alexs-team'));
    expect(Onboarding::current()->invited_at)->not->toBeNull();
});

it('goes away for good once every job is done', function (): void {
    setUpStarterHome()->update(['invited_at' => now()]);
    $garage = Item::query()->where('name', 'Garage')->sole();
    app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Tool chest', 'type' => ItemType::Bin->value, 'parent_id' => $garage->id, 'metadata' => null]);
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Weeknight chili']);

    Native::visit('/dashboard')->assertSet('gettingStarted', null);
});

it('hides the checklist when asked', function (): void {
    setUpStarterHome();

    Native::visit('/dashboard')->tap('getting-started-hide')->assertSet('gettingStarted', null)->assertDontSee('Get started');

    expect(Onboarding::current()->hidden_at)->not->toBeNull();
});

it('offers set-up again after it was skipped', function (): void {
    Onboarding::begin(42)->skip();

    Native::visit('/dashboard')
        ->assertSee('0 of 4 done')
        ->assertSee('Put something away')
        ->tap('getting-started-set-up')
        ->assertNavigatedTo('/setup');
});

it('tells Sunny about a finished set-up it couldn’t report earlier', function (): void {
    Onboarding::begin(42)->update(['skipped_at' => now()]);

    Native::visit('/dashboard');

    expect(Onboarding::current()->reported_at)->not->toBeNull();
    Saloon::assertSent(MarkOnboardedRequest::class);
});

it('teaches an empty place with one-tap starter Storage', function (): void {
    setUpStarterHome();
    $garage = Item::query()->where('name', 'Garage')->sole();

    Native::visit('/inventory/'.$garage->id)
        ->assertSee('Nothing stored here yet')
        ->assertSee('Tap one to add it to Garage')
        ->assertSee('Camping box')
        ->assertSee('Put something inside')
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
