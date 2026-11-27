<?php

use App\Http\Integrations\Sunny\Requests\DuplicateItemRequest;
use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\PendingWrite;
use GuzzleHttp\Exception\ConnectException;
use GuzzleHttp\Psr7\Request as GuzzleRequest;
use Illuminate\Support\Str;
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

/**
 * @return list<array<string, mixed>>
 */
function sunnyCopiesOf(int $id, int $count, int $firstId = 90): array
{
    return array_map(fn (int $copyId): array => [
        ...Item::find($id)->toArray(),
        'id' => $copyId,
        'photos' => [[
            'id' => $copyId * 10, 'item_id' => $copyId, 'position' => 0, 'client_uuid' => null,
            'url' => "https://sunny.example/photos/{$copyId}.jpg", 'thumb_url' => null,
            'created_at' => '2026-11-16T10:00:00Z', 'updated_at' => '2026-11-16T10:00:00Z',
        ]],
    ], range($firstId, $firstId + $count - 1));
}

it('makes one copy next to the original, with its photos', function (): void {
    Saloon::fake([DuplicateItemRequest::class => MockResponse::make(['data' => sunnyCopiesOf(12, 1)], 201)]);

    Native::visit('/inventory/12')
        ->tap('Make copies')
        ->assertSet('showCopies', true)
        ->assertSet('copies', 1)
        ->assertSee('Each copy gets the same name, details and photos, but starts empty inside.')
        ->assertSee('Going in Basement')
        ->assertSee('Make 1 copy')
        ->assertAccessible()
        ->tap('item-copies-submit')
        ->assertSet('showCopies', false)
        ->assertToastShown(fn (array $params): bool => $params['message'] === '1 copy made'
            && $params['variant'] === 'success'
            && $params['action'] === ['id' => 'view-copies', 'label' => 'View']
            && $params['data'] === ['item' => 12])
        ->emitNative(ToastActionPressed::class, ['toastId' => 'copies', 'actionId' => 'view-copies', 'data' => ['item' => 12.0]])
        ->assertNavigatedTo('/inventory/11');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof DuplicateItemRequest
        && $request->resolveEndpoint() === '/teams/family/items/12/duplicate'
        && $request->body()->get('count') === 1
        && Str::isUuid($request->body()->get('client_uuid')));

    expect(Item::find(90))->toMatchArray(['name' => 'Holiday decorations', 'parent_id' => 11, 'team_id' => 1])
        ->and(ItemPhoto::where('item_id', 90)->pluck('url')->all())->toBe(['https://sunny.example/photos/90.jpg']);
});

it('makes several copies at once', function (): void {
    Saloon::fake([DuplicateItemRequest::class => MockResponse::make(['data' => sunnyCopiesOf(12, 6)], 201)]);

    $screen = Native::visit('/inventory/12')->tap('Make copies');

    foreach (range(1, 5) as $tap) {
        $screen->tap('item-copies-more');
    }

    $screen->assertSet('copies', 6)
        ->assertSee('Make 6 copies')
        ->tap('item-copies-submit')
        ->assertToastShown(fn (array $params): bool => $params['message'] === '6 copies made');

    Saloon::assertSent(fn (Request $request): bool => $request instanceof DuplicateItemRequest && $request->body()->get('count') === 6);

    expect(Item::where('name', 'Holiday decorations')->where('parent_id', 11)->count())->toBe(7)
        ->and(ItemPhoto::whereBetween('item_id', [90, 95])->count())->toBe(6);
});

it('keeps the number of copies between 1 and 25', function (): void {
    $screen = Native::visit('/inventory/12')
        ->tap('Make copies')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-copies-fewer')
        ->call('changeCopies', -1)
        ->assertSet('copies', 1)
        ->assertSee('Up to 25 at a time');

    foreach (range(1, 24) as $tap) {
        $screen->tap('item-copies-more');
    }

    $screen->assertSet('copies', 25)
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-copies-more')
        ->call('changeCopies', 1)
        ->assertSet('copies', 25)
        ->assertSee('Make 25 copies')
        ->tap('item-copies-fewer')
        ->assertSet('copies', 24);

    Saloon::assertNothingSent();
});

it('says where copies of something outside any place go', function (): void {
    Saloon::fake([DuplicateItemRequest::class => MockResponse::make(['data' => sunnyCopiesOf(11, 2)], 201)]);

    Native::visit('/inventory/11')
        ->tap('Make copies')
        ->assertSee('Going with the places in your home')
        ->tap('item-copies-more')
        ->tap('item-copies-submit')
        ->assertToastShown(fn (array $params): bool => $params['message'] === '2 copies made')
        ->emitNative(ToastActionPressed::class, ['toastId' => 'copies', 'actionId' => 'view-copies', 'data' => ['item' => 11]])
        ->assertNavigatedTo('/inventory');
});

it('leaves View to the screen of the item that was copied', function (): void {
    Native::visit('/inventory/12')
        ->emitNative(ToastActionPressed::class, ['toastId' => 'copies', 'actionId' => 'view-copies', 'data' => ['item' => 13]])
        ->emitNative(ToastActionPressed::class, ['toastId' => 'other', 'actionId' => 'go-to-restored', 'data' => ['item' => 12]])
        ->assertNoNavigation();
});

it('copies nothing while offline and lets them try again', function (): void {
    $attempts = 0;
    $uuids = [];
    Saloon::fake([DuplicateItemRequest::class => function (PendingRequest $pending) use (&$attempts, &$uuids): MockResponse {
        $uuids[] = $pending->body()->get('client_uuid');

        return $attempts++ === 0
            ? MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('POST', 'https://sunny.example')), $pending))
            : MockResponse::make(['data' => sunnyCopiesOf(12, 3)], 201);
    }]);

    $screen = Native::visit('/inventory/12')
        ->tap('Make copies')
        ->tap('item-copies-more')
        ->tap('item-copies-more')
        ->tap('item-copies-submit')
        ->assertSet('showCopies', true)
        ->assertSet('copies', 3)
        ->assertSee('You’re offline')
        ->assertSee('Try again when you’re connected.')
        ->assertDontSee('Nothing’s been copied yet.')
        ->assertSee('Try again');

    expect(Item::count())->toBe(15);

    $screen->tap('item-copies-submit')
        ->assertSet('showCopies', false)
        ->assertSet('copiesProblem', null)
        ->assertToastShown(fn (array $params): bool => $params['message'] === '3 copies made');

    expect(Item::count())->toBe(18)
        ->and($uuids)->toHaveCount(2)
        ->and($uuids[0])->toBe($uuids[1]);
});

it('asks for a new set of copies when the number changes after a failed try', function (): void {
    $uuids = [];
    Saloon::fake([DuplicateItemRequest::class => function (PendingRequest $pending) use (&$uuids): MockResponse {
        $uuids[] = $pending->body()->get('client_uuid');

        return MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('POST', 'https://sunny.example')), $pending));
    }]);

    Native::visit('/inventory/12')
        ->tap('Make copies')
        ->tap('item-copies-submit')
        ->tap('item-copies-more')
        ->tap('item-copies-submit');

    expect($uuids)->toHaveCount(2)
        ->and($uuids[0])->not->toBe($uuids[1]);
});

it('explains when Sunny refuses to make copies', function (int $status, array $body, string $message): void {
    Saloon::fake([DuplicateItemRequest::class => MockResponse::make($body, $status)]);

    Native::visit('/inventory/12')
        ->tap('Make copies')
        ->tap('item-copies-submit')
        ->assertSet('showCopies', true)
        ->assertSee('Couldn’t make copies')
        ->assertSee($message.' Nothing’s been copied yet.')
        ->assertSee('Try again')
        ->assertNativeNotCalled('ToastKit.Show');

    expect(Item::count())->toBe(15);
})->with([
    'server error' => [500, [], 'The server couldn’t make copies right now.'],
    'no permission' => [403, [], 'You no longer have permission to add things to this team.'],
    'gone' => [404, [], 'Holiday decorations is no longer on the server.'],
    'invalid' => [422, ['errors' => ['count' => ['The count field must not be greater than 25.']]], 'The count field must not be greater than 25.'],
]);

it('sends unsent changes before copying, so the copies match', function (): void {
    $sent = [];
    Saloon::fake([
        SaveRecordRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = 'save';

            return MockResponse::make(['data' => [...Item::find(12)->toArray(), 'name' => 'Christmas decorations']]);
        },
        SyncRequest::class => MockResponse::make([], 503),
        DuplicateItemRequest::class => function (PendingRequest $pending) use (&$sent): MockResponse {
            $sent[] = 'duplicate';

            return MockResponse::make(['data' => [[...sunnyCopiesOf(12, 1)[0], 'name' => 'Christmas decorations']]], 201);
        },
    ]);
    app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Christmas decorations', 'type' => 'bin', 'parent_id' => 11, 'metadata' => null], 12);

    Native::visit('/inventory/12')
        ->tap('Make copies')
        ->tap('item-copies-submit')
        ->assertSet('copiesProblem', null)
        ->assertToastShown(fn (array $params): bool => $params['message'] === '1 copy made');

    expect($sent)->toBe(['save', 'duplicate'])
        ->and(PendingWrite::count())->toBe(0)
        ->and(Item::find(90)->name)->toBe('Christmas decorations');
});

it('waits while unsent changes can’t reach Sunny', function (int $status, string $title): void {
    Saloon::fake([
        SaveRecordRequest::class => $status === 0
            ? MockResponse::make()->throw(fn (PendingRequest $pending) => new FatalRequestException(new ConnectException('Could not resolve host', new GuzzleRequest('PATCH', 'https://sunny.example')), $pending))
            : MockResponse::make([], $status),
        DuplicateItemRequest::class => MockResponse::make(['data' => sunnyCopiesOf(12, 1)], 201),
    ]);
    app(SunnyOutbox::class)->queue('items', 1, ['name' => 'Christmas decorations', 'type' => 'bin', 'parent_id' => 11, 'metadata' => null], 12);

    Native::visit('/inventory/12')
        ->tap('Make copies')
        ->tap('item-copies-submit')
        ->assertSee($title)
        ->assertSee('Try again');

    Saloon::assertNotSent(DuplicateItemRequest::class);
    expect(PendingWrite::count())->toBe(1);
})->with([
    'offline' => [0, 'You’re offline'],
    'server error' => [500, 'Still sending your changes'],
]);
