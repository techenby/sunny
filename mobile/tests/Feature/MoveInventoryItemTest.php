<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Item;
use App\Models\PendingWrite;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Http\PendingRequest;

beforeEach(fn () => seedSunnyData());

it('moves an item to another place from its page', function () {
    Native::visit('/inventory/10')
        ->tap('item-move')
        ->assertSet('showParentPicker', true)
        ->assertSet('parentBrowseId', null)
        ->tap('move-parent-11')
        ->assertSee('Put it in Basement')
        ->tap('move-parent-choose')
        ->assertSet('showParentPicker', false)
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Moved to Basement' && $params['variant'] === 'success')
        ->assertElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'item-parent');

    expect(Item::find(10)->parent_id)->toBe(11)
        ->and(PendingWrite::sole()->payload)->toBe(['parent_id' => 11]);
});

it('sends only the new place to the server', function () {
    Saloon::fake([SaveRecordRequest::class => fn (PendingRequest $request): MockResponse => MockResponse::make(['data' => [
        ...Item::find(10)->only('id', 'team_id', 'type', 'name', 'metadata', 'created_at', 'updated_at'), ...$request->body()->all(),
    ]])]);

    Native::visit('/inventory/10')
        ->tap('item-move')
        ->tap('move-parent-11')
        ->tap('move-parent-choose');
    app(SunnyWrites::class)->push('saved-token');

    Saloon::assertSentCount(1);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->getMethod() === Method::PATCH
        && $request->resolveEndpoint() === '/teams/family/items/10'
        && $request->body()->all() === ['parent_id' => 11]);
    expect(PendingWrite::count())->toBe(0)
        ->and(Item::find(10)->parent_id)->toBe(11);
});

it('still sends a rename made offline before the move', function () {
    Saloon::fake([SaveRecordRequest::class => fn (PendingRequest $request): MockResponse => MockResponse::make(['data' => [
        ...Item::find(10)->only('id', 'team_id', 'type', 'name', 'metadata', 'created_at', 'updated_at'), ...$request->body()->all(),
    ]])]);

    Native::visit('/inventory/10/edit')
        ->input('edit-item-name', 'Big tent')
        ->tap('edit-item-submit')
        ->assertWentBack();
    Native::visit('/inventory/10')
        ->tap('item-move')
        ->tap('move-parent-11')
        ->tap('move-parent-choose');

    expect(Item::find(10)->only('name', 'parent_id'))->toBe(['name' => 'Big tent', 'parent_id' => 11]);

    app(SunnyWrites::class)->push('saved-token');

    Saloon::assertSentCount(1);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/items/10'
        && $request->body()->get('name') === 'Big tent'
        && $request->body()->get('parent_id') === 11);
    expect(PendingWrite::count())->toBe(0)
        ->and(Item::find(10)->only('name', 'parent_id'))->toBe(['name' => 'Big tent', 'parent_id' => 11]);
});

it('takes an item out of every place', function () {
    Saloon::fake([SaveRecordRequest::class => fn (PendingRequest $request): MockResponse => MockResponse::make(['data' => [
        ...Item::find((int) basename($request->getUrl()))->only('id', 'team_id', 'type', 'name', 'metadata', 'created_at', 'updated_at'), ...$request->body()->all(),
    ]])]);

    Native::visit('/inventory/10')
        ->tap('item-move')
        ->tap('move-parent-choose')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Moved to “Not in a place yet”' && $params['variant'] === 'success')
        ->assertSee('Not in a place yet');

    expect(Item::find(10)->parent_id)->toBeNull();

    app(SunnyWrites::class)->push('saved-token');

    Saloon::assertSentCount(1);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->getMethod() === Method::PATCH
        && $request->resolveEndpoint() === '/teams/family/items/10'
        && $request->body()->all() === ['parent_id' => null]);
    expect(PendingWrite::count())->toBe(0)
        ->and(Item::find(10)->parent_id)->toBeNull();
});

it('moves a place back to the places in the home', function () {
    Item::findOrFail(1)->update(['parent_id' => 6]);

    Native::visit('/inventory/1')
        ->tap('item-move')
        ->assertSee('Put it with the places in your home')
        ->tap('move-parent-choose')
        ->assertToastShown(fn (array $params): bool => $params['message'] === 'Now one of the places in your home' && $params['variant'] === 'success')
        ->assertSee('One of the places in your home');

    expect(Item::find(1)->parent_id)->toBeNull();
});

it('leaves an item alone when it is put back where it was', function () {
    Native::visit('/inventory/10')
        ->tap('item-move')
        ->tap('move-parent-6')
        ->tap('move-parent-choose');

    expect(Item::find(10)->parent_id)->toBe(6)
        ->and(PendingWrite::count())->toBe(0);
});

it('does not offer the item or anything inside it as a new place', function () {
    expect(array_column(Native::visit('/inventory/6')->tap('item-move')->get('parentChoices'), 'name'))->toBe([
        'Basement', 'Holiday decorations', 'Ornaments', 'String lights', 'Spare light bulbs',
        'Kitchen', 'Pantry', 'Basmati rice', 'Canned tomatoes', 'Olive oil',
    ]);
});
