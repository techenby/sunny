<?php

use App\Http\Integrations\Sunny\Requests\ImportRecipeRequest;
use App\Models\PendingWrite;
use App\Models\Recipe;
use App\NativeComponents\EditRecipe;
use Native\Mobile\AsyncTask;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Laravel\Facades\Saloon;

const PANCAKES_URL = 'https://www.seriouseats.com/buttermilk-pancakes';

function importedRecipe(array $overrides = []): array
{
    return [
        'id' => 77, 'team_id' => 1, 'parent_id' => null, 'name' => 'Buttermilk Pancakes', 'source' => PANCAKES_URL,
        'servings' => '4', 'prep_time' => '10 min', 'cook_time' => '15 min', 'total_time' => '25 min',
        'description' => 'Tall, fluffy and tangy.', 'ingredients' => [['type' => 'item', 'text' => '2 cups flour']],
        'instructions' => [['type' => 'item', 'text' => 'Whisk the flour.']], 'notes' => null, 'nutrition' => null,
        'tags' => ['breakfast'], 'photo_url' => null, 'thumb_url' => null,
        'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
        ...$overrides,
    ];
}

function connectionLost(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $request) => new FatalRequestException(new RuntimeException('Network is unreachable'), $request));
}

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'test-token']);
});

it('offers to import once a link is in Source', function (): void {
    Native::visit('/recipes/create')
        ->assertDontSee('Import this recipe')
        ->input('recipe-source', 'Grandma’s recipe box')
        ->assertDontSee('Import this recipe')
        ->input('recipe-source', PANCAKES_URL)
        ->assertSee('seriouseats.com')
        ->assertSee('/buttermilk-pancakes')
        ->assertSee('This page can be read to fill in the name, photo, ingredients, steps and times for you.')
        ->assertSee('Import this recipe')
        ->assertAccessible();
});

it('imports the recipe and opens it to check over', function (): void {
    Saloon::fake([ImportRecipeRequest::class => MockResponse::make(['data' => importedRecipe()], 201)]);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->tap('recipe-import')
        ->assertReplacedWith('/recipes/77/edit')
        ->follow()
        ->assertScreen(EditRecipe::class)
        ->assertSet('name', 'Buttermilk Pancakes')
        ->assertSet('prepTime', '10 min')
        ->assertSee('Imported and saved')
        ->assertSee('This was filled in from the page. Have a look over it and fix anything that didn’t come across right.')
        ->tap('recipe-import-dismiss')
        ->assertDontSee('Imported and saved');

    Saloon::assertSent(fn (ImportRecipeRequest $request): bool => $request->resolveEndpoint() === '/teams/family/recipes/import'
        && $request->body()->all()['url'] === PANCAKES_URL);
    expect(Recipe::find(77))->name->toBe('Buttermilk Pancakes')
        ->and(PendingWrite::count())->toBe(0);
});

it('reads the page in the background while showing progress', function (): void {
    $async = AsyncTask::fake();
    Saloon::fake([ImportRecipeRequest::class => MockResponse::make(['data' => importedRecipe()], 201)]);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->tap('recipe-import');

    $async->assertDispatchedTimes(1);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->set('importing', true)
        ->assertSee('Reading the recipe from seriouseats.com… this takes a few seconds.')
        ->assertSee('Importing')
        ->assertElement('progress_bar')
        ->assertElement('column', fn (array $node): bool => ($node['ref'] ?? null) === 'create-recipe-submit')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'create-recipe-submit');
});

it('leaves out the progress bar on iOS, where it only draws a second spinner', function (): void {
    Native::visit('/recipes/create', platform: 'ios')
        ->input('recipe-source', PANCAKES_URL)
        ->set('importing', true)
        ->assertSee('Reading the recipe from seriouseats.com… this takes a few seconds.')
        ->assertMissingElement('progress_bar');
});

it('explains when Sunny couldn’t open the page at all', function (): void {
    Saloon::fake([ImportRecipeRequest::class => MockResponse::make(['message' => 'Sunny couldn’t open that page.', 'errors' => ['url' => ['Sunny couldn’t open that page.']]], 424)]);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->tap('recipe-import')
        ->assertSee('Couldn’t open that page')
        ->assertSee('That site didn’t allow the page to be read. Try another link, or fill it in yourself.')
        ->assertDontSee('No recipe on that page')
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-source' && ($node['props']['is_error'] ?? false) === true)
        ->assertSee('Change link')
        ->assertSee('Fill it in myself');
});

it('explains when Sunny finds no recipe at the link', function (string $url, string $message): void {
    $recipes = Recipe::count();
    Saloon::fake([ImportRecipeRequest::class => MockResponse::make(['message' => 'No recipe', 'errors' => ['url' => ['Sunny couldn’t find a recipe at that address.']]], 422)]);

    Native::visit('/recipes/create')
        ->input('recipe-source', $url)
        ->tap('recipe-import')
        ->assertNoNavigation()
        ->assertSee('No recipe on that page')
        ->assertSee($message)
        ->assertElement('outlined_text_input', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-source' && ($node['props']['is_error'] ?? false) === true)
        ->tap('recipe-import-change-link')
        ->assertSet('source', '')
        ->assertDontSee('No recipe on that page');

    expect(Recipe::count())->toBe($recipes);
})->with([
    'a home page' => ['https://joythebaker.com', 'That looks like a home page. Try the link to the recipe itself, or fill it in yourself.'],
    'another page' => ['https://joythebaker.com/about', 'No recipe was found at that address. Try the link to the recipe itself, or fill it in yourself.'],
]);

it('lets them fill the recipe in by hand instead', function (): void {
    Saloon::fake([ImportRecipeRequest::class => MockResponse::make(['errors' => ['url' => ['No recipe']]], 422)]);

    Native::visit('/recipes/create')
        ->input('recipe-source', 'https://joythebaker.com')
        ->tap('recipe-import')
        ->tap('recipe-import-fill-in')
        ->assertDontSee('No recipe on that page')
        ->assertDontSee('Import this recipe')
        ->assertSet('source', 'https://joythebaker.com')
        ->input('recipe-source', PANCAKES_URL)
        ->assertSee('Import this recipe');
});

it('says it can’t import offline without contacting Sunny', function (): void {
    Saloon::fake([]);
    Native::fakeBridge()->respondTo('Network.Status', ['connected' => false, 'type' => 'unknown']);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->tap('recipe-import')
        ->assertNoNavigation()
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected.')
        ->assertSet('source', PANCAKES_URL)
        ->assertSee('Try again')
        ->assertSee('Fill it in myself');

    Saloon::assertNothingSent();
    expect(PendingWrite::count())->toBe(0);
});

it('treats a dropped connection as offline', function (): void {
    Saloon::fake([ImportRecipeRequest::class => connectionLost()]);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->tap('recipe-import')
        ->assertSee('You’re offline')
        ->assertSet('importing', false);
});

it('retries with the same client uuid so Sunny never saves it twice', function (): void {
    $uuids = [];
    Saloon::fake([ImportRecipeRequest::class => function (PendingRequest $request) use (&$uuids): MockResponse {
        $uuids[] = $request->body()->all()['client_uuid'];

        return count($uuids) === 1 ? connectionLost() : MockResponse::make(['data' => importedRecipe()], 201);
    }]);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->tap('recipe-import')
        ->assertSee('You’re offline')
        ->tap('recipe-import-retry')
        ->assertReplacedWith('/recipes/77/edit');

    expect($uuids)->toHaveCount(2)
        ->and($uuids[0])->toBeString()->not->toBeEmpty()
        ->and($uuids[1])->toBe($uuids[0])
        ->and(Recipe::query()->whereKey(77)->count())->toBe(1);
});

it('ignores another tap while an import is running', function (): void {
    Saloon::fake([]);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->set('importing', true)
        ->call('importRecipe')
        ->assertSet('importing', true);

    Saloon::assertNothingSent();
});

it('uses a new client uuid for a different link', function (): void {
    Native::fakeBridge()->respondTo('Network.Status', ['connected' => false]);

    $screen = Native::visit('/recipes/create')->input('recipe-source', PANCAKES_URL)->tap('recipe-import');
    $first = $screen->get('importUuid');

    $screen->input('recipe-source', 'https://www.seriouseats.com/waffles')->tap('recipe-import');

    expect($screen->get('importUuid'))->not->toBe($first);
    $screen->input('recipe-source', PANCAKES_URL)->assertDontSee('You’re offline');
});

it('explains other failures and offers a retry', function (int $status, string $message): void {
    Saloon::fake([ImportRecipeRequest::class => MockResponse::make([], $status)]);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->tap('recipe-import')
        ->assertSee('Couldn’t import')
        ->assertSee($message)
        ->assertSee('Try again');
})->with([
    'rate limited' => [429, 'That’s a lot of imports in a row. Wait a minute and try again.'],
    'server down' => [503, 'The server is unavailable right now. Try again in a moment.'],
]);

it('only offers import on a new recipe', function (): void {
    Native::visit('/recipes/1/edit')
        ->assertSet('source', 'https://www.allrecipes.com/recipe/buttermilk-pancakes/')
        ->assertDontSee('Import this recipe')
        ->assertDontSee('Imported and saved');
});

it('asks them to sign in before importing', function (): void {
    Saloon::fake([]);
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => '']);

    Native::visit('/recipes/create')
        ->input('recipe-source', PANCAKES_URL)
        ->tap('recipe-import')
        ->assertSee('Sign in to import recipes.');

    Saloon::assertNothingSent();
});
