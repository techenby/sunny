<?php

use App\Http\Integrations\Sunny\Requests\DeleteRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Models\PendingWrite;
use App\Models\Recipe;
use Native\Mobile\Events\Alert\ButtonPressed;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;

beforeEach(fn () => seedSunnyData());

it('asks before deleting a recipe', function () {
    Native::visit('/recipes/1')
        ->tap('delete-recipe')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['id'] === 'delete-recipe'
            && $params['title'] === 'Delete Buttermilk Pancakes?'
            && $params['message'] === '“Buttermilk Pancakes” will be deleted for everyone on your team.')
        ->emitNative(ButtonPressed::class, ['index' => 0, 'label' => 'Cancel', 'id' => 'delete-recipe'])
        ->assertNoNavigation();

    expect(Recipe::find(1))->not->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('says a recipe’s remixes won’t be deleted with it', function (array $moreRemixes, string $message) {
    foreach ($moreRemixes as $id => $name) {
        Recipe::create([...Recipe::find(4)->getAttributes(), 'id' => $id, 'name' => $name]);
    }

    Native::visit('/recipes/3')
        ->tap('delete-recipe')
        ->assertNativeCalled('Dialog.Alert', fn (array $params): bool => $params['message'] === $message);
})->with([
    'one remix' => [[], '“Grandma’s Lasagna” will be deleted for everyone on your team. Its remix won’t be deleted.'],
    'two remixes' => [[90 => 'Spinach Lasagna'], '“Grandma’s Lasagna” will be deleted for everyone on your team. Its 2 remixes won’t be deleted.'],
]);

it('deletes a recipe once confirmed and goes back', function () {
    Native::visit('/recipes/1')
        ->tap('delete-recipe')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-recipe'])
        ->assertWentBack();

    expect(Recipe::find(1))->toBeNull()
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'deletes'))->toBe(['resource' => 'recipes', 'record_id' => 1, 'deletes' => true]);
    Native::visit('/recipes')->assertDontSee('Buttermilk Pancakes')->assertSee('Weeknight Chili');
});

it('sends the deletion to Sunny when the phone is online', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([DeleteRecordRequest::class => MockResponse::make([], 204), SyncRequest::class => MockResponse::make([], 503)]);

    Native::visit('/recipes/1')
        ->tap('delete-recipe')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-recipe'])
        ->assertWentBack();

    Saloon::assertSent(fn (DeleteRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/recipes/1' && $request->getMethod() === Method::DELETE);
    expect(Recipe::find(1))->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('keeps an offline deletion queued and the recipe hidden until Sunny can be reached', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([DeleteRecordRequest::class => MockResponse::make([], 503)]);

    Native::visit('/recipes/1')
        ->tap('delete-recipe')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-recipe'])
        ->assertWentBack();
    Saloon::assertSent(DeleteRecordRequest::class);
    seedSunnyData();

    expect(Recipe::find(1))->toBeNull()
        ->and(PendingWrite::sole()->only('resource', 'record_id', 'deletes', 'error'))->toBe(['resource' => 'recipes', 'record_id' => 1, 'deletes' => true, 'error' => null]);
    Native::visit('/recipes')->assertDontSee('Buttermilk Pancakes');
});

it('deletes a recipe that never reached Sunny without queueing anything', function () {
    $id = app(SunnyOutbox::class)->queue('recipes', 1, ['name' => 'Banana Bread']);

    Native::visit('/recipes/'.$id)
        ->tap('delete-recipe')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-recipe'])
        ->assertWentBack();

    expect(Recipe::find($id))->toBeNull()->and(PendingWrite::count())->toBe(0);
});

it('keeps a remix and stops linking to its original once that is deleted', function () {
    Native::visit('/recipes/3')
        ->tap('delete-recipe')
        ->emitNative(ButtonPressed::class, ['index' => 1, 'label' => 'Delete', 'id' => 'delete-recipe']);

    expect(Recipe::find(4)->parent_id)->toBe(3);
    Native::visit('/recipes/4')
        ->assertSee('Veggie Lasagna')
        ->assertDontSee('Remixed from')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'related-recipe-3');
});
