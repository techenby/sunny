<?php

use App\Http\Integrations\Sunny\Requests\GetSharedRecipeRequest;
use App\Http\Integrations\Sunny\Requests\SaveSharedRecipeRequest;
use App\Models\Recipe;
use App\NativeComponents\SharedRecipe;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Str;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

const SHARE_TOKEN = '0b7f1c2e-6a1d-4c55-9a51-2f3e4d5c6b7a';

beforeEach(function (): void {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
});

/**
 * @return array<string, mixed>
 */
function sharedRecipePayload(): array
{
    return [
        'id' => 501, 'team_id' => 9, 'name' => 'Grandma’s Rolls', 'source' => 'https://smittenkitchen.com/rolls',
        'servings' => '12 rolls', 'prep_time' => '20 min', 'cook_time' => '25 min', 'total_time' => '3 hours',
        'description' => 'Soft and buttery.',
        'ingredients' => [['type' => 'heading', 'text' => 'Dough'], ['type' => 'item', 'text' => '4 cups flour'], ['type' => 'item', 'text' => '1 cup milk']],
        'instructions' => [['type' => 'item', 'text' => 'Mix everything.'], ['type' => 'item', 'text' => 'Bake until golden.']],
        'notes' => 'Freeze before baking.', 'nutrition' => "Calories: 180\nProtein: 5 g",
        'share_token' => SHARE_TOKEN, 'share_url' => 'https://sunny.example/recipes/shared/'.SHARE_TOKEN,
        'photo_url' => null, 'thumb_url' => null,
    ];
}

/**
 * @return array<string, mixed>
 */
function savedSharedRecipe(int $id = 77): array
{
    return [
        ...collect(sharedRecipePayload())->except(['share_token', 'share_url'])->all(),
        'id' => $id, 'team_id' => 1, 'parent_id' => null, 'source' => 'https://sunny.example/recipes/shared/'.SHARE_TOKEN,
        'tags' => [], 'share_url' => null, 'created_at' => now()->toIso8601String(), 'updated_at' => now()->toIso8601String(),
    ];
}

function sharedRecipeConnectionLost(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('GET', 'https://sunny.example')), $pending));
}

it('sends a shared link to the welcome screen before anything has synced', function (): void {
    Native::visit('/recipes/shared/'.SHARE_TOKEN)->assertReplacedWith('/');

    Saloon::assertNothingSent();
});

it('paints the screen with its back button before leaving it in mount', function (): void {
    Native::visit('/recipes/shared/'.SHARE_TOKEN)->assertReplacedWith('/');

    $publishes = Native::fakeBridge()->publishes;

    expect($publishes)->toHaveCount(2)
        ->and($publishes[0]['props'])->toMatchArray(['nav_back' => true, 'current_uri' => '/recipes/shared/'.SHARE_TOKEN])
        ->and(json_encode($publishes[0]))->toContain('"ref":"shared-recipe-loading"')->toContain('activity_indicator');
});

it('shows a shared recipe read-only with one action to save it', function (): void {
    seedSunnyData();
    Saloon::fake([GetSharedRecipeRequest::class => MockResponse::make(['data' => sharedRecipePayload()])]);

    Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->assertScreen(SharedRecipe::class)
        ->assertSet('state', 'found')
        ->assertSee('Grandma’s Rolls')
        ->assertSee('Soft and buttery.')
        ->assertSee('Shared with you · View only')
        ->assertSee('12 rolls')
        ->assertSee('smittenkitchen.com')
        ->assertSee('For 12 rolls')
        ->assertSee('Dough')
        ->assertSee('4 cups flour')
        ->assertSee('Bake until golden.')
        ->assertSee('Freeze before baking.')
        ->assertSee('Protein: 5 g')
        ->assertSee('Save to Family')
        ->assertMissingElement('pressable', fn (array $node): bool => in_array($node['ref'] ?? null, ['edit-recipe', 'recipe-more'], true))
        ->assertAccessible();

    Saloon::assertSent(fn (Request $request): bool => $request instanceof GetSharedRecipeRequest
        && $request->resolveEndpoint() === '/shared-recipes/'.SHARE_TOKEN);
});

it('says when a link has been turned off', function (int $status): void {
    seedSunnyData();
    Saloon::fake([GetSharedRecipeRequest::class => MockResponse::make(['message' => 'Not found'], $status)]);

    Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->assertSet('state', 'gone')
        ->assertSee('This recipe isn’t shared anymore')
        ->assertSee('Whoever sent it may have turned sharing off, or the link isn’t quite right. Ask them to send it again.')
        ->assertDontSee('Save to Family')
        ->assertAccessible()
        ->tap('shared-recipe-go-to-recipes')
        ->assertNavigatedTo('/recipes');
})->with([404]);

it('says it is offline and opens the recipe on Try again', function (): void {
    seedSunnyData();
    $attempts = 0;
    Saloon::fake([GetSharedRecipeRequest::class => function () use (&$attempts): MockResponse {
        return $attempts++ === 0 ? sharedRecipeConnectionLost() : MockResponse::make(['data' => sharedRecipePayload()]);
    }]);

    Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->assertSet('state', 'offline')
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected.')
        ->assertSee('Try again')
        ->assertAccessible()
        ->tap('shared-recipe-retry')
        ->assertSet('state', 'found')
        ->assertSee('Grandma’s Rolls');
});

it('explains other problems opening a link', function (): void {
    seedSunnyData();
    Saloon::fake([GetSharedRecipeRequest::class => MockResponse::make([], 503)]);

    Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->assertSet('state', 'error')
        ->assertSee('Couldn’t open this recipe')
        ->assertSee('The server is unavailable right now. Try again in a moment.')
        ->assertSee('Try again');
});

it('saves the shared recipe to the current team', function (): void {
    seedSunnyData();
    Saloon::fake([
        GetSharedRecipeRequest::class => MockResponse::make(['data' => sharedRecipePayload()]),
        SaveSharedRecipeRequest::class => MockResponse::make(['data' => savedSharedRecipe()], 201),
    ]);

    Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->tap('shared-recipe-save')
        ->assertSee('Saved to your recipes')
        ->assertSee('In Family')
        ->assertDontSee('Save to Family')
        ->assertAccessible()
        ->tap('shared-recipe-open')
        ->assertNavigatedTo('/recipes/77');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof SaveSharedRecipeRequest
        && $request->resolveEndpoint() === '/shared-recipes/'.SHARE_TOKEN.'/save'
        && $request->body()->all()['team_id'] === 1
        && Str::isUuid($request->body()->all()['client_uuid']));

    expect(Recipe::find(77))->team_id->toBe(1)->name->toBe('Grandma’s Rolls')->source->toBe('https://sunny.example/recipes/shared/'.SHARE_TOKEN);
});

it('says when the recipe is already in their recipes', function (): void {
    seedSunnyData();
    Saloon::fake([
        GetSharedRecipeRequest::class => MockResponse::make(['data' => sharedRecipePayload()]),
        SaveSharedRecipeRequest::class => MockResponse::make(['data' => savedSharedRecipe(5)], 200),
    ]);

    Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->tap('shared-recipe-save')
        ->assertSee('Already in your recipes')
        ->assertSee('In Family')
        ->tap('shared-recipe-open')
        ->assertNavigatedTo('/recipes/5');
});

it('saves nothing while offline and retries with the same uuid', function (): void {
    seedSunnyData();
    $uuids = [];
    Saloon::fake([
        GetSharedRecipeRequest::class => MockResponse::make(['data' => sharedRecipePayload()]),
        SaveSharedRecipeRequest::class => function (PendingRequest $pending) use (&$uuids): MockResponse {
            $uuids[] = $pending->body()->all()['client_uuid'];

            return count($uuids) === 1 ? sharedRecipeConnectionLost() : MockResponse::make(['data' => savedSharedRecipe()], 200);
        },
    ]);

    $screen = Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->tap('shared-recipe-save')
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected.')
        ->assertDontSee('Nothing’s been saved yet.')
        ->assertSee('Try again');

    expect(Recipe::find(77))->toBeNull();

    $screen->tap('shared-recipe-save')->assertSee('Already in your recipes');

    expect($uuids)->toHaveCount(2)
        ->and($uuids[0])->toBe($uuids[1])
        ->and(Recipe::find(77))->not->toBeNull();
});

it('shows the turned-off state when the link stops working before saving', function (): void {
    seedSunnyData();
    Saloon::fake([
        GetSharedRecipeRequest::class => MockResponse::make(['data' => sharedRecipePayload()]),
        SaveSharedRecipeRequest::class => MockResponse::make(['message' => 'Not found'], 404),
    ]);

    Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->tap('shared-recipe-save')
        ->assertSet('state', 'gone')
        ->assertSee('This recipe isn’t shared anymore');
});

it('explains when Sunny refuses to save', function (): void {
    seedSunnyData();
    Saloon::fake([
        GetSharedRecipeRequest::class => MockResponse::make(['data' => sharedRecipePayload()]),
        SaveSharedRecipeRequest::class => MockResponse::make(['message' => 'No'], 500),
    ]);

    Native::visit('/recipes/shared/'.SHARE_TOKEN)
        ->tap('shared-recipe-save')
        ->assertSee('Couldn’t save')
        ->assertSee('The server couldn’t save it right now. Nothing’s been saved yet.')
        ->assertSee('Try again');
});
