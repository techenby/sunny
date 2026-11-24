<?php

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    Http::fake([
        'example.com/apple-cider-donuts' => Http::response(fakeRecipeHtml([
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Apple Cider Donuts',
            'recipeYield' => '12',
            'totalTime' => 'PT18M',
            'recipeIngredient' => ['1 cup apple cider', '2 cups flour'],
            'recipeInstructions' => [['@type' => 'HowToStep', 'text' => 'Reduce the cider.']],
        ])),
        'example.com/not-a-recipe' => Http::response('<html><body>Hello</body></html>'),
        'example.com/missing' => Http::response('', 404),
    ]);
});

test('guests cannot import recipes', function () {
    $this->postJson(route('api.recipes.import', 'household'), ['url' => 'https://example.com/apple-cider-donuts'])->assertUnauthorized();
});

test('import creates a recipe from a page and keeps its source', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => 'https://example.com/apple-cider-donuts'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'Apple Cider Donuts')
        ->assertJsonPath('data.source', 'https://example.com/apple-cider-donuts')
        ->assertJsonPath('data.servings', '12');

    $recipe = Recipe::sole();

    expect($recipe->team_id)->toBe($user->current_team_id)
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->instructions)->toHaveCount(1);
});

test('import saves a recipe whose name the team already used', function (bool $deleted) {
    $user = User::factory()->create();
    $existing = Recipe::factory()->for($user->currentTeam)->create(['name' => 'Apple Cider Donuts']);
    $deleted && $existing->delete();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => 'https://example.com/apple-cider-donuts', 'client_uuid' => '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d'])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'apple-cider-donuts-1');

    expect($existing->fresh()->slug)->toBe('apple-cider-donuts');
})->with(['kept' => false, 'deleted' => true]);

test('import returns the existing recipe when a client uuid is retried', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $payload = ['url' => 'https://example.com/apple-cider-donuts', 'client_uuid' => $uuid];

    $first = $this->actingAs($user)->postJson(route('api.recipes.import', $user->currentTeam), $payload)->assertCreated();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), $payload)
        ->assertOk()
        ->assertJsonPath('data.id', $first->json('data.id'));

    expect(Recipe::count())->toBe(1);
    Http::assertSentCount(1);
});

test('import returns the existing recipe when a retry wins the race to insert it', function () {
    $user = User::factory()->create();
    $uuid = '9b2f6c1e-3d4a-4f5b-8c7d-1e2f3a4b5c6d';
    $raced = createAfterLookup('recipes', 'client_uuid', fn () => Recipe::factory()->for($user->currentTeam)->create(['name' => 'Apple Cider Donuts', 'client_uuid' => $uuid]), lookup: 2);

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => 'https://example.com/apple-cider-donuts', 'client_uuid' => $uuid])
        ->assertOk()
        ->assertJsonPath('data.id', $raced->model->id);

    expect(Recipe::count())->toBe(1);
    Http::assertSentCount(1);
});

test('import trims page values that are too long to save', function () {
    Http::fake([
        'example.com/long-values' => Http::response(fakeRecipeHtml([
            '@type' => 'Recipe',
            'url' => 'https://example.com/' . str_repeat('a', 500),
            'name' => str_repeat('Apple Pie ', 30),
            'recipeYield' => str_repeat('8 slices ', 10),
            'prepTime' => str_repeat('about an hour ', 10),
        ])),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => 'https://example.com/long-values'])
        ->assertCreated()
        ->assertJsonPath('data.name', trim(mb_substr(str_repeat('Apple Pie ', 30), 0, 255)))
        ->assertJsonPath('data.source', 'https://example.com/long-values')
        ->assertJsonPath('data.servings', trim(mb_substr(str_repeat('8 slices ', 10), 0, 50)))
        ->assertJsonPath('data.prep_time', trim(mb_substr(str_repeat('about an hour ', 10), 0, 50)));
});

test('import keeps a source url up to 500 characters and rejects a longer one', function () {
    $url = 'https://example.com/apple-cider-donuts?ref=' . str_repeat('a', 457);
    Http::fake([$url => Http::response(fakeRecipeHtml(['@type' => 'Recipe', 'name' => 'Apple Cider Donuts']))]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => $url])
        ->assertCreated()
        ->assertJsonPath('data.source', $url);

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => $url . 'a'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');
});

test('import explains when a page has no recipe', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => 'https://example.com/not-a-recipe'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url' => 'Sunny couldn’t find a recipe at that address.']);

    expect(Recipe::count())->toBe(0);
});

test('import tells a page it couldn’t open apart from one without a recipe', function (string $url) {
    Http::fake([
        'example.com/paywalled' => Http::response('Payment Required', 402),
        'example.com/unreachable' => fn () => throw new ConnectionException('Connection timed out'),
    ]);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => $url])
        ->assertStatus(424)
        ->assertJsonValidationErrors(['url' => 'Sunny couldn’t open that page.']);

    expect(Recipe::count())->toBe(0);
})->with([
    'page not found' => 'https://example.com/missing',
    'blocked' => 'https://example.com/paywalled',
    'connection failed' => 'https://example.com/unreachable',
]);

test('import validates the url', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => 'file:///etc/passwd'])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('url');

    Http::assertNothingSent();
});

test('import is limited to members of the team', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $other->currentTeam), ['url' => 'https://example.com/apple-cider-donuts'])
        ->assertForbidden();

    expect(Recipe::count())->toBe(0);
});

test('import never fetches an address inside the network', function () {
    $this->resolveHostsTo(['169.254.169.254']);
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => 'https://example.com/apple-cider-donuts'])
        ->assertStatus(424)
        ->assertJsonValidationErrors('url');

    Http::assertNothingSent();
    expect(Recipe::count())->toBe(0);
});
