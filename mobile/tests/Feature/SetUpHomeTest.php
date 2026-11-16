<?php

use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Http\Integrations\Sunny\Requests\CreateTokenRequest;
use App\Http\Integrations\Sunny\Requests\ImportRecipeRequest;
use App\Http\Integrations\Sunny\Requests\MarkOnboardedRequest;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Item;
use App\Models\Onboarding;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineStep;
use Native\Mobile\Testing\Native;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

function seedEmptyHome(): void
{
    app(SunnyStore::class)->applySnapshot([
        'teams' => [['id' => 1, 'name' => 'Alex’s Team', 'slug' => 'alexs-team']],
        'recipes' => [], 'items' => [], 'checklists' => [], 'checklist_items' => [],
        'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
        'synced_at' => now()->toIso8601String(),
    ]);
    app(SunnyStore::class)->rememberAccount(42);
}

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'test-token'])
        ->respondTo('SecureStorage.Set', ['success' => true])
        ->respondTo('SecureStorage.Delete', ['success' => true]);
    seedEmptyHome();
});

it('sends a brand-new account to set-up after logging in', function (): void {
    Saloon::fake([CreateTokenRequest::class => MockResponse::make(['id' => 42, 'name' => 'Alex Rivera', 'onboarded_at' => null, 'token' => 'test-token'])]);

    Native::visit('/login')->input('login-email', 'alex@example.com')->input('login-password', 'secret')
        ->tap('login-submit')->assertReplacedWith('/setup');

    expect(Onboarding::current())->first_name->toBe('Alex');
});

it('sends an existing account straight to the summary', function (array $user): void {
    Saloon::fake([CreateTokenRequest::class => MockResponse::make([...$user, 'id' => 42, 'name' => 'Alex Rivera', 'token' => 'test-token'])]);

    Native::visit('/login')->input('login-email', 'alex@example.com')->input('login-password', 'secret')
        ->tap('login-submit')->assertReplacedWith('/dashboard');

    expect(Onboarding::current())->toBeNull();
})->with([
    'onboarded on another device' => [['onboarded_at' => '2026-11-01T12:00:00Z']],
    'server without onboarding' => [[]],
]);

it('greets the person and offers places and starters', function (): void {
    Onboarding::begin(42, 'Alex Rivera');

    Native::visit('/setup')
        ->assertSee('What’s in your home, Alex?')
        ->assertSee('Kitchen pantry')
        ->assertSee('Shed')
        ->assertSee('Groceries list')
        ->assertSee('3 places, a groceries list and a bedtime routine')
        ->assertAccessible();
});

it('leaves set-up for anyone who isn’t brand new', function (): void {
    Native::visit('/setup')->assertReplacedWith('/dashboard');
});

it('toggles places and starters and adds the person’s own place', function (): void {
    Onboarding::begin(42);

    Native::visit('/setup')
        ->tap('setup-place-0')
        ->tap('setup-place-5')
        ->tap('setup-own-place')
        ->input('setup-new-place', '  Laundry room ')
        ->tap('setup-add-place')
        ->tap('setup-starter-bedtime')
        ->tap('setup-starter-recipe')
        ->assertSet('places', ['Garage', 'Hall closet', 'Shed', 'Laundry room'])
        ->assertSet('bedtime', false)
        ->assertSet('recipe', true)
        ->assertSee('4 places, a groceries list and a sample recipe');
});

it('creates the places and starters, imports the sample recipe and marks onboarding done', function (): void {
    Onboarding::begin(42);
    Saloon::fake([
        ImportRecipeRequest::class => MockResponse::make(['data' => [
            'id' => 90, 'team_id' => 1, 'name' => 'Apple Cider Donuts', 'source' => 'https://safelydelish.com/apple-cider-donuts/',
            'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
        ]], 201),
        MarkOnboardedRequest::class => MockResponse::make(['id' => 42]),
    ]);

    Native::visit('/setup')
        ->tap('setup-starter-recipe')
        ->tap('setup-submit')
        ->assertReplacedWith('/dashboard');

    expect(Item::query()->orderByDesc('id')->get()->map(fn (Item $item): array => [$item->name, $item->type]))->toEqual(collect([
        ['Kitchen pantry', ItemType::Location], ['Garage', ItemType::Location], ['Hall closet', ItemType::Location],
    ]))
        ->and(Checklist::sole()->name)->toBe('Groceries')
        ->and(ChecklistItem::query()->orderByDesc('id')->pluck('name')->all())->toBe(['Milk', 'Eggs', 'Bread', 'Butter', 'Bananas', 'Coffee'])
        ->and(Routine::sole())->name->toBe('Bedtime')->time_of_day->toBe(TimeOfDay::Evening)->frequency->toBe(RoutineFrequency::Daily)
        ->and(RoutineStep::query()->orderByDesc('id')->pluck('name')->all())->toBe(['Bath', 'Pajamas', 'Brush teeth', 'Read a story'])
        ->and(Recipe::sole()->name)->toBe('Apple Cider Donuts');

    $onboarding = Onboarding::current();
    expect($onboarding->set_up_at)->not->toBeNull()
        ->and($onboarding->reported_at)->not->toBeNull()
        ->and($onboarding->starters())->toHaveCount(6);

    Saloon::assertSent(fn (Request $request): bool => $request instanceof ImportRecipeRequest
        && $request->resolveEndpoint() === '/teams/alexs-team/recipes/import'
        && $request->body()->get('url') === 'https://safelydelish.com/apple-cider-donuts/');
});

it('finishes set-up without the recipe when the import fails', function (): void {
    Onboarding::begin(42);
    Saloon::fake([
        ImportRecipeRequest::class => MockResponse::make(['errors' => ['url' => ['No recipe']]], 422),
        MarkOnboardedRequest::class => MockResponse::make([], 500),
    ]);

    Native::visit('/setup')->tap('setup-starter-recipe')->tap('setup-submit')->assertReplacedWith('/dashboard');

    expect(Recipe::count())->toBe(0)
        ->and(Onboarding::current())->set_up_at->not->toBeNull()->reported_at->toBeNull();
});

it('skips set-up without creating anything', function (): void {
    Onboarding::begin(42);
    Saloon::fake([MarkOnboardedRequest::class => MockResponse::make(['id' => 42])]);

    Native::visit('/setup')->tap('setup-skip')->assertReplacedWith('/dashboard');

    expect(Item::count())->toBe(0)
        ->and(Onboarding::current())->skipped_at->not->toBeNull()->reported_at->not->toBeNull()
        ->and(Onboarding::needsSetUp())->toBeFalse();
});
