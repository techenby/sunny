<?php

use App\Http\Integrations\Sunny\Requests\CopyRecipeRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Models\Recipe;
use App\Models\Team;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Str;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
});

function addOtherTeams(): void
{
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Lake Cabin', 'slug' => 'lake-cabin']);
    Team::create(['id' => 3, 'server' => SunnyStore::server(), 'name' => 'Grandma’s House', 'slug' => 'grandmas-house']);
}

/**
 * @return array<string, mixed>
 */
function sunnyCopyOfRecipe(int $teamId, int $id = 90): array
{
    return [...Recipe::find(1)->toArray(), 'id' => $id, 'team_id' => $teamId, 'parent_id' => 1, 'share_token' => null];
}

function copyMenuItem(): Closure
{
    return fn (array $node): bool => $node['props']['id'] === 'recipe-copy-to-team';
}

it('only offers copying when the person has another team', function (): void {
    Native::visit('/recipes/1')
        ->assertElement('top_bar_action', fn (array $node): bool => $node['props']['id'] === 'recipe-remix')
        ->assertMissingElement('top_bar_action', copyMenuItem());

    addOtherTeams();

    Native::visit('/recipes/1')
        ->assertElement('top_bar_action', fn (array $node): bool => $node['props']['id'] === 'recipe-copy-to-team' && $node['props']['label'] === 'Copy to another team');
});

it('copies the recipe to the team they pick', function (): void {
    addOtherTeams();
    Saloon::fake([CopyRecipeRequest::class => MockResponse::make(['data' => sunnyCopyOfRecipe(2)], 201)]);

    Native::visit('/recipes/1')
        ->tap('Copy to another team')
        ->assertSet('showCopy', true)
        ->assertSet('copyTeamId', 3)
        ->assertSee('The copy gets everything, tags and photo too. This recipe stays here, just as it is.')
        ->assertSee('From Family')
        ->assertSee('Grandma’s House')
        ->assertSee('Lake Cabin')
        ->assertSee('Copy to Grandma’s House')
        ->assertAccessible()
        ->tap('recipe-copy-team-2')
        ->assertSet('copyTeamId', 2)
        ->assertSee('Copy to Lake Cabin')
        ->tap('recipe-copy-submit')
        ->assertSet('showCopy', false)
        ->assertSee('Copied to Lake Cabin')
        ->assertSee('Switch team in Settings to see it there')
        ->assertSee('Buttermilk Pancakes');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof CopyRecipeRequest
        && $request->resolveEndpoint() === '/teams/family/recipes/1/copy'
        && $request->body()->all()['team_id'] === 2
        && Str::isUuid($request->body()->all()['client_uuid']));

    expect(Recipe::find(90))->team_id->toBe(2)->parent_id->toBe(1)->name->toBe('Buttermilk Pancakes')
        ->and(Recipe::find(1))->team_id->toBe(1);
});

it('copies nothing while offline and retries with the same uuid', function (): void {
    addOtherTeams();
    $uuids = [];
    Saloon::fake([CopyRecipeRequest::class => function (PendingRequest $pending) use (&$uuids): MockResponse {
        $uuids[] = $pending->body()->all()['client_uuid'];

        return count($uuids) === 1
            ? MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('POST', 'https://sunny.example')), $pending))
            : MockResponse::make(['data' => sunnyCopyOfRecipe(3)], 200);
    }]);

    $screen = Native::visit('/recipes/1')
        ->tap('Copy to another team')
        ->tap('recipe-copy-submit')
        ->assertSet('showCopy', true)
        ->assertSet('copyTeamId', 3)
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected.')
        ->assertDontSee('Nothing’s been copied yet.')
        ->assertSee('Try again');

    expect(Recipe::find(90))->toBeNull();

    $screen->tap('recipe-copy-submit')
        ->assertSet('showCopy', false)
        ->assertSet('copyProblem', null)
        ->assertSee('Copied to Grandma’s House');

    expect($uuids)->toHaveCount(2)
        ->and($uuids[0])->toBe($uuids[1])
        ->and(Recipe::find(90))->team_id->toBe(3);
});

it('keeps the uuid when the sheet is closed and opened again after a failure', function (): void {
    addOtherTeams();
    $uuids = [];
    Saloon::fake([CopyRecipeRequest::class => function (PendingRequest $pending) use (&$uuids): MockResponse {
        $uuids[] = $pending->body()->all()['client_uuid'];

        return MockResponse::make([], 503);
    }]);

    Native::visit('/recipes/1')
        ->tap('Copy to another team')
        ->tap('recipe-copy-submit')
        ->tap('recipe-copy-cancel')
        ->assertSet('showCopy', false)
        ->tap('Copy to another team')
        ->assertDontSee('Couldn’t copy')
        ->tap('recipe-copy-submit');

    expect($uuids)->toHaveCount(2)->and($uuids[0])->toBe($uuids[1]);
});

it('explains when Sunny refuses to copy', function (int $status, string $message): void {
    addOtherTeams();
    Saloon::fake([CopyRecipeRequest::class => MockResponse::make(['message' => 'No'], $status)]);

    Native::visit('/recipes/1')
        ->tap('Copy to another team')
        ->tap('recipe-copy-submit')
        ->assertSet('showCopy', true)
        ->assertSee('Couldn’t copy')
        ->assertSee($message.' Nothing’s been copied yet.')
        ->assertSee('Try again')
        ->assertDontSee('Copied to');

    expect(Recipe::where('team_id', 3)->count())->toBe(0);
})->with([
    'server error' => [500, 'The server couldn’t copy it right now.'],
    'no permission' => [403, 'You no longer have permission to copy this recipe.'],
    'gone' => [404, 'Buttermilk Pancakes is no longer on the server.'],
    'not their team' => [422, 'Grandma’s House isn’t one of your teams anymore.'],
]);

it('waits for unsent changes to reach Sunny before copying', function (): void {
    addOtherTeams();
    Saloon::fake([CopyRecipeRequest::class => MockResponse::make(['data' => sunnyCopyOfRecipe(3)], 201)]);
    app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Weekend Pancakes'], 1);

    Native::visit('/recipes/1')
        ->tap('Copy to another team')
        ->tap('recipe-copy-submit')
        ->assertSee('Still sending your changes')
        ->assertSee('Try again');

    Saloon::assertNotSent(CopyRecipeRequest::class);
});
