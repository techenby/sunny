<?php

use App\Http\Integrations\Sunny\Requests\ShareRecipeRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Models\Recipe;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

const SHARE_URL = 'https://sunny.example/recipes/shared/0b7f1c2e-6a1d-4c55-9a51-2f3e4d5c6b7a';

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
});

/**
 * @return array<string, mixed>
 */
function sharedRecipeRecord(?string $shareUrl): array
{
    return [...Recipe::find(1)->toArray(), 'share_token' => $shareUrl ? basename($shareUrl) : null, 'share_url' => $shareUrl];
}

function connectionDropped(): MockResponse
{
    return MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('PUT', 'https://sunny.example')), $pending));
}

it('offers Share in the More menu', function (): void {
    Native::visit('/recipes/1')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-more'
            && array_column(array_column(array_filter($node['children'], fn (array $child): bool => $child['type'] === 'top_bar_action'), 'props'), 'id') === ['recipe-edit', 'recipe-remix', 'recipe-share', 'delete-recipe']);
});

it('explains the public link while it is off', function (): void {
    Native::visit('/recipes/1')
        ->tap('Share')
        ->assertSet('showShare', true)
        ->assertSee('Turn on a public link and anyone who has it can see this recipe.')
        ->assertSee('Only your home can see it')
        ->assertSee('Opens in any browser')
        ->assertSee('Turn it off any time')
        ->assertSee('Turn on public link')
        ->assertSee('Not now')
        ->assertDontSee('Send link')
        ->assertAccessible();
});

it('turns the link on and sends it with the share sheet', function (): void {
    Native::fakeBridge()->withClipboard();
    Saloon::fake([ShareRecipeRequest::class => MockResponse::make(['data' => sharedRecipeRecord(SHARE_URL)])]);

    Native::visit('/recipes/1')
        ->tap('Share')
        ->tap('recipe-share-submit')
        ->assertSet('showShare', true)
        ->assertSet('shareProblem', null)
        ->assertSee('Your link is on. Anyone who has it can open this recipe in a browser.')
        ->assertSee('Anyone with the link can see it')
        ->assertSee('sunny.example/recipes/shared/0b7f1c2e-6a1d-4c55-9a51-2f3e4d5c6b7a')
        ->assertSee('Turn off link')
        ->assertSee('Send link')
        ->assertSee('Done')
        ->assertAccessible()
        ->tap('recipe-share-copy')
        ->assertCopied(SHARE_URL)
        ->assertSee('Copied')
        ->tap('recipe-share-submit')
        ->assertSharedUrl(SHARE_URL);

    Saloon::assertSent(fn (Request $request): bool => $request instanceof ShareRecipeRequest
        && $request->getMethod() === Method::PUT
        && $request->resolveEndpoint() === '/teams/family/recipes/1/share');

    expect(Recipe::find(1)->share_url)->toBe(SHARE_URL);
});

it('opens with the link on when the recipe already has one', function (): void {
    Recipe::find(1)->update(['share_url' => SHARE_URL]);

    Native::visit('/recipes/1')
        ->tap('Share')
        ->assertSee('Send link')
        ->assertSee('Turn off link')
        ->assertDontSee('Turn on public link');

    Saloon::assertNothingSent();
});

it('says so when the link cannot be copied', function (): void {
    Recipe::find(1)->update(['share_url' => SHARE_URL]);

    Native::visit('/recipes/1')
        ->tap('Share')
        ->tap('recipe-share-copy')
        ->assertSet('linkCopied', false)
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Unable to copy the link. Please try again.' && $params['variant'] === 'error');
});

it('turns the link off', function (): void {
    Recipe::find(1)->update(['share_url' => SHARE_URL]);
    Saloon::fake([ShareRecipeRequest::class => MockResponse::make(['data' => sharedRecipeRecord(null)])]);

    Native::visit('/recipes/1')
        ->tap('Share')
        ->tap('recipe-share-turn-off')
        ->assertSet('showShare', true)
        ->assertSee('Turn on public link')
        ->assertSee('Only your home can see it')
        ->assertDontSee('Send link');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof ShareRecipeRequest && $request->getMethod() === Method::DELETE);

    expect(Recipe::find(1)->share_url)->toBeNull();
});

it('keeps the recipe private while offline and lets them try again', function (): void {
    $attempts = 0;
    Saloon::fake([ShareRecipeRequest::class => function () use (&$attempts): MockResponse {
        return $attempts++ === 0 ? connectionDropped() : MockResponse::make(['data' => sharedRecipeRecord(SHARE_URL)]);
    }]);

    $screen = Native::visit('/recipes/1')
        ->tap('Share')
        ->tap('recipe-share-submit')
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected. The recipe is still private, and no link has been made.')
        ->assertSee('Try again')
        ->assertSee('Cancel');

    expect(Recipe::find(1)->share_url)->toBeNull();

    $screen->tap('recipe-share-submit')
        ->assertSet('shareProblem', null)
        ->assertSee('Send link');

    expect(Recipe::find(1)->share_url)->toBe(SHARE_URL);
});

it('keeps the link working when turning it off offline', function (): void {
    Recipe::find(1)->update(['share_url' => SHARE_URL]);
    $methods = [];
    Saloon::fake([ShareRecipeRequest::class => function (PendingRequest $pending) use (&$methods): MockResponse {
        $methods[] = $pending->getMethod();

        return count($methods) === 1 ? connectionDropped() : MockResponse::make(['data' => sharedRecipeRecord(null)]);
    }]);

    $screen = Native::visit('/recipes/1')
        ->tap('Share')
        ->tap('recipe-share-turn-off')
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected. The link still works for now.');

    expect(Recipe::find(1)->share_url)->toBe(SHARE_URL);

    $screen->tap('recipe-share-submit')->assertSee('Turn on public link');

    expect($methods)->toBe([Method::DELETE, Method::DELETE])
        ->and(Recipe::find(1)->share_url)->toBeNull();
});

it('explains when Sunny refuses to change the link', function (int $status, string $message): void {
    Saloon::fake([ShareRecipeRequest::class => MockResponse::make(['message' => 'No'], $status)]);

    Native::visit('/recipes/1')
        ->tap('Share')
        ->tap('recipe-share-submit')
        ->assertSee('Couldn’t turn on the link')
        ->assertSee($message.' The recipe is still private, and no link has been made.')
        ->assertSee('Try again');
})->with([
    'server error' => [500, 'Sunny couldn’t change the link right now.'],
    'no permission' => [403, 'You no longer have permission to share this recipe.'],
    'gone' => [404, 'Buttermilk Pancakes is no longer on Sunny.'],
]);

it('waits for a new recipe to reach Sunny before making a link', function (): void {
    $id = app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Fresh Bread']);

    Native::visit('/recipes/'.$id)
        ->tap('Share')
        ->tap('recipe-share-submit')
        ->assertSee('Still sending this recipe')
        ->assertSee('Try again');

    Saloon::assertNotSent(ShareRecipeRequest::class);
});
