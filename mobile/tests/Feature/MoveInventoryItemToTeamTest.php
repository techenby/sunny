<?php

use App\Http\Integrations\Sunny\Requests\MoveItemRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnyTeam;
use App\Models\Item;
use App\Models\PendingWrite;
use App\Models\Team;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Native\Mobile\Testing\Native;
use Saloon\Exceptions\Request\FatalRequestException;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;
use Saloon\Http\Request;
use Saloon\Laravel\Facades\Saloon;
use Victorycodedev\ToastKit\Events\ToastActionPressed;

beforeEach(function (): void {
    seedSunnyData();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
});

function addTeamsToMoveTo(): void
{
    Team::create(['id' => 2, 'server' => SunnyStore::server(), 'name' => 'Lake Cabin', 'slug' => 'lake-cabin']);
    Team::create(['id' => 3, 'server' => SunnyStore::server(), 'name' => 'Grandma’s House', 'slug' => 'grandmas-house']);
}

/**
 * @param  list<int>  $movedIds
 * @return array<string, mixed>
 */
function sunnyMovedItem(int $id, int $teamId, array $movedIds): array
{
    return [
        'data' => [...Item::find($id)->toArray(), 'team_id' => $teamId, 'parent_id' => null, 'photos' => []],
        'moved_item_ids' => $movedIds,
    ];
}

function itemRef(string $ref): Closure
{
    return fn (array $node): bool => ($node['ref'] ?? null) === $ref;
}

it('only offers moving to another team when the person has one', function (): void {
    Native::visit('/inventory/12')
        ->assertElement('top_bar_action', fn (array $node): bool => $node['props']['id'] === 'item-make-copies')
        ->assertMissingElement('top_bar_action', fn (array $node): bool => $node['props']['id'] === 'item-move-to-team');

    addTeamsToMoveTo();

    Native::visit('/inventory/12')
        ->assertElement('top_bar_action', fn (array $node): bool => $node['props']['id'] === 'item-move-to-team' && $node['props']['label'] === 'Move to another team');
});

it('moves the item and everything inside it to the team they pick', function (): void {
    addTeamsToMoveTo();
    Saloon::fake([MoveItemRequest::class => MockResponse::make(sunnyMovedItem(12, 2, [12, 13, 14]))]);

    $basement = Native::visit('/inventory/11')
        ->assertElement('pressable', itemRef('item-child-12'))
        ->tap('item-child-12');

    $basement->follow()
        ->tap('Move to another team')
        ->assertSet('showTeamMove', true)
        ->assertSet('teamMoveTeamId', 3)
        ->assertSee('Holiday decorations and the 2 things inside come along and leave Family.')
        ->assertSee('Grandma’s House')
        ->assertSee('Lake Cabin')
        ->assertSee('Goes to Not in a place yet')
        ->assertSee('Move to Grandma’s House')
        ->assertAccessible()
        ->tap('item-team-move-team-2')
        ->assertSet('teamMoveTeamId', 2)
        ->assertSee('Move to Lake Cabin')
        ->tap('item-team-move-submit')
        ->assertWentBack()
        ->goBack()
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Moved to Lake Cabin'
            && $params['variant'] === 'success'
            && $params['action'] === ['id' => 'switch-team', 'label' => 'Switch']
            && $params['data'] === ['team_id' => 2, 'item_id' => 12])
        ->assertMissingElement('pressable', itemRef('item-child-12'))
        ->assertElement('pressable', itemRef('item-child-15'))
        ->assertAccessible();

    Saloon::assertSent(fn (Request $request): bool => $request instanceof MoveItemRequest
        && $request->resolveEndpoint() === '/teams/family/items/12/move'
        && $request->body()->all() === ['team_id' => 2]);

    expect(Item::find(12))->toMatchArray(['team_id' => 2, 'parent_id' => null])
        ->and(Item::find(13))->toMatchArray(['team_id' => 2, 'parent_id' => 12])
        ->and(Item::find(14))->toMatchArray(['team_id' => 2, 'parent_id' => 12])
        ->and(Item::find(15)->team_id)->toBe(1);
});

it('lands on the place it was in when it was opened from somewhere else', function (): void {
    addTeamsToMoveTo();
    Saloon::fake([MoveItemRequest::class => MockResponse::make(sunnyMovedItem(13, 3, [13]))]);

    $screen = Native::visit('/inventory/13')
        ->tap('Move to another team')
        ->assertSee('String lights leaves Family.')
        ->tap('item-team-move-submit')
        ->assertReplacedWith('/inventory/12');

    $screen->follow()
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Moved to Grandma’s House')
        ->assertMissingElement('pressable', itemRef('item-child-13'));
});

it('goes back with a toast when the item was at the top level', function (): void {
    addTeamsToMoveTo();
    Saloon::fake([MoveItemRequest::class => MockResponse::make(sunnyMovedItem(11, 3, [11, 12, 13, 14, 15]))]);

    Native::visit('/inventory/11')
        ->tap('Move to another team')
        ->assertSee('Goes with the places in your home')
        ->tap('item-team-move-submit')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Moved Basement to Grandma’s House' && ! isset($params['action']))
        ->assertWentBack();

    expect(Item::whereIn('id', [11, 12, 13, 14, 15])->pluck('team_id')->unique()->all())->toBe([3]);
});

it('switches to the team it went to and opens it there', function (): void {
    addTeamsToMoveTo();
    Saloon::fake([MoveItemRequest::class => MockResponse::make(sunnyMovedItem(13, 3, [13]))]);

    Native::visit('/inventory/13')
        ->tap('Move to another team')
        ->tap('item-team-move-submit')
        ->follow()
        ->emitNative(ToastActionPressed::class, ['toastId' => 'moved', 'actionId' => 'switch-team', 'data' => ['team_id' => 3.0, 'item_id' => 13.0]])
        ->assertReplacedWith('/inventory/13');

    expect(app(SunnyTeam::class)->current()->id)->toBe(3)
        ->and(Item::find(13)->team_id)->toBe(3);

    Saloon::assertSentCount(1);
});

it('opens the team’s inventory when the moved item isn’t on the phone there', function (): void {
    addTeamsToMoveTo();
    Saloon::fake([MoveItemRequest::class => MockResponse::make(sunnyMovedItem(13, 3, [13]))]);

    $basement = Native::visit('/inventory/13')
        ->tap('Move to another team')
        ->tap('item-team-move-submit')
        ->follow();

    Item::whereKey(13)->delete();

    $basement->emitNative(ToastActionPressed::class, ['toastId' => 'moved', 'actionId' => 'switch-team', 'data' => ['team_id' => 3, 'item_id' => 13]])
        ->assertReplacedWith('/inventory');

    expect(app(SunnyTeam::class)->current()->id)->toBe(3);
});

it('moves nothing while offline and lets them try again', function (): void {
    addTeamsToMoveTo();
    $attempts = 0;
    Saloon::fake([MoveItemRequest::class => function (PendingRequest $pending) use (&$attempts): MockResponse {
        return $attempts++ === 0
            ? MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('POST', 'https://sunny.example')), $pending))
            : MockResponse::make(sunnyMovedItem(12, 2, [12, 13, 14]));
    }]);

    $screen = Native::visit('/inventory/12')
        ->tap('Move to another team')
        ->tap('item-team-move-team-2')
        ->tap('item-team-move-submit')
        ->assertSet('showTeamMove', true)
        ->assertSet('teamMoveTeamId', 2)
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected.')
        ->assertDontSee('Nothing’s been moved yet.')
        ->assertSee('Try again')
        ->assertNoNavigation();

    expect(Item::where('team_id', 1)->count())->toBe(15);

    $screen->tap('item-team-move-submit')
        ->assertReplacedWith('/inventory/11');

    expect(Item::whereIn('id', [12, 13, 14])->pluck('team_id')->unique()->all())->toBe([2]);
});

it('explains when Sunny refuses the move', function (int $status, string $message): void {
    addTeamsToMoveTo();
    Saloon::fake([MoveItemRequest::class => MockResponse::make([], $status), SyncRequest::class => MockResponse::make([], 503)]);

    Native::visit('/inventory/12')
        ->tap('Move to another team')
        ->tap('item-team-move-submit')
        ->assertSet('showTeamMove', true)
        ->assertSee('Couldn’t move it')
        ->assertSee($message.' Nothing’s been moved yet.')
        ->assertSee('Try again')
        ->assertNoNavigation();

    expect(Item::where('team_id', 1)->count())->toBe(15);
})->with([
    'server error' => [500, 'The server couldn’t move it right now.'],
    'no permission' => [403, 'You no longer have permission to move things in Family.'],
    'gone' => [404, 'Holiday decorations isn’t in Family on the server anymore.'],
    'not their team' => [422, 'Grandma’s House isn’t one of your teams anymore.'],
]);

it('treats a move that already reached Sunny as done', function (): void {
    addTeamsToMoveTo();
    $snapshot = json_decode(file_get_contents(base_path('tests/Fixtures/sunny.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach (['recipes', 'items', 'checklists', 'routines'] as $type) {
        $snapshot[$type] = array_map(fn (array $record): array => $record + ['team_id' => 1], $snapshot[$type]);
    }
    $snapshot['items'] = array_map(fn (array $item): array => $item['id'] === 13 ? [...$item, 'team_id' => 3, 'parent_id' => null] : $item, $snapshot['items']);
    Saloon::fake([
        MoveItemRequest::class => MockResponse::make([], 404),
        SyncRequest::class => MockResponse::make([
            ...$snapshot,
            'routine_occurrences' => sunnyRoutineOccurrences(),
            'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family'], ['id' => 2, 'name' => 'Lake Cabin', 'slug' => 'lake-cabin'], ['id' => 3, 'name' => 'Grandma’s House', 'slug' => 'grandmas-house']],
            'synced_at' => now()->toIso8601String(),
        ]),
    ]);

    Native::visit('/inventory/13')
        ->tap('Move to another team')
        ->tap('item-team-move-submit')
        ->assertReplacedWith('/inventory/12')
        ->follow()
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Moved to Grandma’s House');

    expect(Item::find(13)->team_id)->toBe(3);
});

it('sends unsent changes inside it before moving', function (): void {
    addTeamsToMoveTo();
    $sent = [];
    Saloon::fake([
        SaveRecordRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = 'save';

            return MockResponse::make(['data' => [...Item::find(13)->toArray(), 'name' => 'Fairy lights']]);
        },
        SyncRequest::class => MockResponse::make([], 503),
        MoveItemRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = 'move';

            return MockResponse::make(sunnyMovedItem(12, 3, [12, 13, 14]));
        },
    ]);
    app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Fairy lights', 'type' => 'item', 'parent_id' => 12, 'metadata' => null], 13);

    Native::visit('/inventory/12')
        ->tap('Move to another team')
        ->tap('item-team-move-submit')
        ->assertSet('teamMoveProblem', null)
        ->assertReplacedWith('/inventory/11');

    expect($sent)->toBe(['save', 'move'])
        ->and(PendingWrite::count())->toBe(0)
        ->and(Item::find(13))->toMatchArray(['name' => 'Fairy lights', 'team_id' => 3]);
});

it('waits while unsent changes inside it can’t reach Sunny', function (int $status, string $title): void {
    addTeamsToMoveTo();
    Saloon::fake([
        SaveRecordRequest::class => $status === 0
            ? MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('PATCH', 'https://sunny.example')), $pending))
            : MockResponse::make([], $status),
        MoveItemRequest::class => MockResponse::make(sunnyMovedItem(12, 3, [12, 13, 14])),
    ]);
    app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Fairy lights', 'type' => 'item', 'parent_id' => 12, 'metadata' => null], 13);

    Native::visit('/inventory/12')
        ->tap('Move to another team')
        ->tap('item-team-move-submit')
        ->assertSet('showTeamMove', true)
        ->assertSee($title)
        ->assertSee('Try again')
        ->assertNoNavigation();

    Saloon::assertNotSent(MoveItemRequest::class);
    expect(PendingWrite::count())->toBe(1)
        ->and(Item::find(12)->team_id)->toBe(1);
})->with([
    'offline' => [0, 'You’re offline'],
    'server error' => [500, 'Still sending your changes'],
]);
