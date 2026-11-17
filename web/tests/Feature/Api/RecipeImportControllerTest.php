<?php

use App\Models\Recipe;
use App\Models\User;
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

test('import explains when a page has no recipe', function (string $url) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.import', $user->currentTeam), ['url' => $url])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['url' => 'Sunny couldn’t find a recipe at that address.']);

    expect(Recipe::count())->toBe(0);
})->with([
    'no recipe data' => 'https://example.com/not-a-recipe',
    'page not found' => 'https://example.com/missing',
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
