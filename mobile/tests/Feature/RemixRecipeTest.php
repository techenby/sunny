<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyOutbox;
use App\Http\Integrations\Sunny\SunnyThumbnails;
use App\Models\PendingWrite;
use App\Models\Recipe;
use App\NativeComponents\EditRecipe;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Native\Mobile\Events\Camera\PhotoTaken;
use Native\Mobile\Testing\Native;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;
use Saloon\Repositories\Body\JsonBodyRepository;
use Saloon\Repositories\Body\MultipartBodyRepository;

beforeEach(fn () => seedSunnyData());

afterEach(function (): void {
    File::deleteDirectory(SunnyThumbnails::directory());
    File::deleteDirectory(SunnyOutbox::photoDirectory());
});

function giveThePancakesAPhoto(): Recipe
{
    $thumbUrl = 'https://cdn.sunny.example/recipes/thumbs/pancakes-a1.jpg?signature=one';
    File::ensureDirectoryExists(SunnyThumbnails::directory());
    File::put(SunnyThumbnails::pathFor($thumbUrl), 'pancake-thumb');

    return tap(Recipe::find(1))->update([
        'photo_url' => 'https://cdn.sunny.example/recipes/pancakes.jpg?signature=one',
        'thumb_url' => $thumbUrl,
        'thumb_path' => SunnyThumbnails::pathFor($thumbUrl),
    ]);
}

function bottomBarRefs(array $tree): array
{
    $refs = [];
    $collect = function (array $node, bool $inBar) use (&$collect, &$refs): void {
        $inBar = $inBar || ($node['type'] ?? null) === 'bottom_bar';
        if ($inBar && ($node['type'] ?? null) === 'pressable') {
            $refs[] = $node['ref'] ?? null;
        }
        foreach ($node['children'] ?? [] as $child) {
            $collect($child, $inBar);
        }
    };
    $collect($tree, false);

    return $refs;
}

it('puts Cook and More in the bottom bar, with Edit and Remix in the More menu', function () {
    $screen = Native::visit('/recipes/1')
        ->assertSee('Cook')
        ->assertElement('top_bar_action', fn (array $node): bool => $node['props']['id'] === 'recipe-remix' && $node['props']['label'] === 'Remix')
        ->assertAccessible();

    expect(bottomBarRefs($screen->tree()))->toBe(['cook-recipe', 'recipe-more']);
});

it('offers More without Cook when a recipe has no ingredients or steps', function () {
    Recipe::find(1)->update(['ingredients' => null, 'instructions' => null]);

    expect(bottomBarRefs(Native::visit('/recipes/1')->tree()))->toBe(['edit-recipe', 'recipe-more']);
});

it('opens cook mode from the bottom bar', function () {
    Native::visit('/recipes/1')
        ->tap('cook-recipe')
        ->assertNavigatedTo('/recipes/1/cook');
});

it('saves a remix with the original’s fields and opens it for editing', function () {
    Recipe::find(1)->update(['nutrition' => 'Calories: 320', 'photo_url' => 'https://sunny.example/photos/1.jpg']);
    $original = Recipe::find(1);

    Native::visit('/recipes/1')
        ->tap('Remix')
        ->assertNavigatedTo('/recipes/-1/edit')
        ->follow()
        ->assertScreen(EditRecipe::class)
        ->assertSet('name', 'Buttermilk Pancakes (Remix)')
        ->assertSet('tags', ['breakfast', 'weekend'])
        ->assertSet('ingredients', "2 cups flour\n2 cups buttermilk\n2 eggs\n3 tbsp melted butter\n2 tbsp sugar\n2 tsp baking powder");

    expect(Recipe::find(-1))
        ->name->toBe('Buttermilk Pancakes (Remix)')
        ->parent_id->toBe(1)
        ->team_id->toBe(1)
        ->source->toBe($original->source)
        ->servings->toBe($original->servings)
        ->prep_time->toBe($original->prep_time)
        ->cook_time->toBe($original->cook_time)
        ->total_time->toBe($original->total_time)
        ->description->toBe($original->description)
        ->notes->toBe($original->notes)
        ->nutrition->toBe('Calories: 320')
        ->tags->toBe(['breakfast', 'weekend'])
        ->photo_url->toBe('https://sunny.example/photos/1.jpg')
        ->and(array_column(Recipe::find(-1)->ingredients, 'text'))->toBe(array_column($original->ingredients, 'text'))
        ->and(array_column(Recipe::find(-1)->instructions, 'text'))->toBe(array_column($original->instructions, 'text'))
        ->and(Recipe::find(1)->name)->toBe('Buttermilk Pancakes');

    $write = PendingWrite::sole();
    expect($write->only('resource', 'record_id', 'team_id', 'photo_path', 'error'))
        ->toBe(['resource' => 'recipes', 'record_id' => -1, 'team_id' => 1, 'photo_path' => null, 'error' => null])
        ->and($write->client_uuid)->not->toBeNull()
        ->and($write->payload)->toMatchArray([
            'parent_id' => 1,
            'name' => 'Buttermilk Pancakes (Remix)',
            'ingredients' => "2 cups flour\n2 cups buttermilk\n2 eggs\n3 tbsp melted butter\n2 tbsp sugar\n2 tsp baking powder",
        ])
        ->and($write->payload)->not->toHaveKey('photo');
});

it('shows the original’s photo and thumbnail on a remix Sunny hasn’t saved yet', function () {
    $original = giveThePancakesAPhoto();

    Native::visit('/recipes/1')
        ->tap('Remix')
        ->follow()
        ->assertSet('photoPath', null)
        ->assertElement('image', fn (array $node): bool => ($node['props']['src'] ?? null) === $original->photo_url);
    $listed = collect(Native::visit('/recipes')->get('recipes'))->firstWhere('name', 'Buttermilk Pancakes (Remix)');

    expect($listed['photo'])->toBe($original->thumb_path);

    expect(Recipe::find(-1)->only('photo_url', 'thumb_url', 'thumb_path'))->toBe($original->only('photo_url', 'thumb_url', 'thumb_path'))
        ->and(PendingWrite::sole()->photo_path)->toBeNull()
        ->and(PendingWrite::sole()->payload)->not->toHaveKeys(['photo', 'photo_url', 'thumb_url', 'thumb_path']);
});

it('swaps the original’s photo for the remix’s own once Sunny has saved it', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Http::preventStrayRequests();
    Http::fake(['cdn.sunny.example/*' => Http::response('remix-thumb')]);
    $original = giveThePancakesAPhoto();
    $saved = [
        ...$original->toArray(), 'id' => 90, 'parent_id' => 1, 'name' => 'Buttermilk Pancakes (Remix)',
        'photo_url' => 'https://cdn.sunny.example/recipes/pancakes-remix-b2.jpg?signature=one',
        'thumb_url' => 'https://cdn.sunny.example/recipes/thumbs/pancakes-remix-b2.jpg?signature=one',
    ];
    Saloon::fake([
        SaveRecordRequest::class => MockResponse::make(['data' => $saved], 201),
        SyncRequest::class => MockResponse::make([
            'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family']],
            'recipes' => [$original->toArray(), $saved],
            'items' => [], 'checklists' => [], 'checklist_items' => [], 'routines' => [], 'routine_steps' => [], 'routine_occurrences' => [],
            'synced_at' => '2026-09-23T13:00:00+00:00',
        ]),
    ]);

    Native::visit('/recipes/1')->tap('Remix');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->body() instanceof JsonBodyRepository
        && array_intersect(array_keys($request->body()->all()), ['photo', 'photo_url', 'thumb_url', 'thumb_path']) === []);
    expect(Recipe::find(90))
        ->photo_url->toBe($saved['photo_url'])
        ->thumb_url->toBe($saved['thumb_url'])
        ->thumb_path->toBe(SunnyThumbnails::pathFor($saved['thumb_url']))
        ->and(file_get_contents(Recipe::find(90)->thumb_path))->toBe('remix-thumb')
        ->and(Recipe::find(1)->thumb_path)->toBe($original->thumb_path)
        ->and(file_get_contents($original->thumb_path))->toBe('pancake-thumb');
});

it('leaves the remix without a photo when it is removed before Sunny has saved it', function () {
    giveThePancakesAPhoto();
    $screen = Native::visit('/recipes/1')->tap('Remix')->follow();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    $saved = [...Recipe::find(-1)->toArray(), 'id' => 90, 'photo_url' => null, 'thumb_url' => null];
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $saved], 201), SyncRequest::class => MockResponse::make([], 503)]);

    $screen->tap('edit-recipe-photo-remove')
        ->assertMissingElement('image')
        ->tap('edit-recipe-submit')
        ->assertSet('error', '');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->getMethod() === Method::POST
        && $request->body()->get('parent_id') === 1
        && $request->body()->get('remove_photo') === true);
    expect(Recipe::find(90)->only('photo_url', 'thumb_url', 'thumb_path'))->toBe(['photo_url' => null, 'thumb_url' => null, 'thumb_path' => null])
        ->and(Recipe::find(1)->thumb_path)->toBeFile();
});

it('keeps a photo chosen for the remix before Sunny has saved it', function () {
    giveThePancakesAPhoto();
    $photo = UploadedFile::fake()->image('blueberry.jpg');
    $screen = Native::visit('/recipes/1')->tap('Remix')->follow();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 503)]);

    $screen->emitNative(PhotoTaken::class, ['path' => $photo->getPathname()])
        ->tap('edit-recipe-submit')
        ->assertSet('error', '');

    $remix = Recipe::find(-1);
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->getMethod() === Method::POST
        && $request->body() instanceof MultipartBodyRepository
        && $request->body()->get('parent_id')->value === '1'
        && $request->body()->get('remove_photo')->value === '0'
        && (string) $request->body()->get('photo')->value === file_get_contents($photo->getPathname()));
    expect($remix->photo_url)->toStartWith(SunnyOutbox::photoDirectory())
        ->and(file_get_contents($remix->photo_url))->toBe(file_get_contents($photo->getPathname()))
        ->and($remix->only('thumb_url', 'thumb_path'))->toBe(['thumb_url' => null, 'thumb_path' => null])
        ->and(PendingWrite::sole()->photo_path)->toBe($remix->photo_url);
});

it('lists the new remix under the original', function () {
    Native::visit('/recipes/1')->tap('Remix');

    Native::visit('/recipes/1')
        ->assertSee('Remixes')
        ->assertSee('Buttermilk Pancakes (Remix)');
    Native::visit('/recipes/-1')
        ->assertSee('Remixed from')
        ->assertDontSee('syncing with Sunny');
});

it('sends the remix to Sunny as a new recipe with its parent', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    $saved = [...Recipe::find(1)->toArray(), 'id' => 90, 'parent_id' => 1, 'name' => 'Buttermilk Pancakes (Remix)'];
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $saved], 201), SyncRequest::class => MockResponse::make([], 503)]);

    Native::visit('/recipes/1')->tap('Remix')->assertNavigatedTo('/recipes/-1/edit');

    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/recipes'
        && $request->getMethod() === Method::POST
        && $request->body()->get('parent_id') === 1
        && $request->body()->get('name') === 'Buttermilk Pancakes (Remix)'
        && is_string($request->body()->get('ingredients'))
        && $request->body()->get('client_uuid') !== null);
    expect(Recipe::find(90))->parent_id->toBe(1)->local_id->toBe(-1)
        ->and(PendingWrite::count())->toBe(0);
});

it('remixes a recipe while Sunny is unreachable', function () {
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make([], 503)]);

    Native::visit('/recipes/1')
        ->tap('Remix')
        ->assertSet('error', '')
        ->assertNavigatedTo('/recipes/-1/edit')
        ->follow()
        ->assertSee('Remix of');

    Saloon::assertSent(SaveRecordRequest::class);
    expect(Recipe::find(-1)->parent_id)->toBe(1)
        ->and(PendingWrite::sole()->error)->toBeNull();
});

it('shows the original on Edit right after remixing', function () {
    Native::visit('/recipes/1')
        ->tap('Remix')
        ->follow()
        ->assertNavTitle('Edit recipe')
        ->assertSee('Remix of')
        ->assertSee('Buttermilk Pancakes')
        ->assertSee('The original stays as it is.')
        ->assertSee('Save')
        ->assertAccessible()
        ->tap('recipe-remix-of')
        ->assertNavigatedTo('/recipes/1');
});

it('leaves the remix card off when editing a remix later', function () {
    Native::visit('/recipes/4/edit')
        ->assertSet('justRemixed', false)
        ->assertDontSee('Remix of')
        ->assertMissingElement('pressable', fn (array $node): bool => ($node['ref'] ?? null) === 'recipe-remix-of');
});

it('opens the remix after saving it, leaving the original under it', function () {
    Native::visit('/recipes/1')
        ->tap('Remix')
        ->follow()
        ->input('recipe-name', 'Blueberry Pancakes')
        ->tap('edit-recipe-submit')
        ->assertSet('error', '')
        ->assertReplacedWith('/recipes/-1');

    expect(Recipe::find(-1)->name)->toBe('Blueberry Pancakes')
        ->and(Recipe::find(1)->name)->toBe('Buttermilk Pancakes')
        ->and(PendingWrite::sole()->payload)->toMatchArray(['name' => 'Blueberry Pancakes', 'parent_id' => 1]);
});

it('goes back after editing a recipe that wasn’t just remixed', function () {
    Native::visit('/recipes/4/edit')
        ->tap('edit-recipe-submit')
        ->assertWentBack();
});
