<?php

use App\Http\Integrations\Sunny\Requests\SaveRecordRequest;
use App\Http\Integrations\Sunny\Requests\SyncRequest;
use App\Http\Integrations\Sunny\SunnyStore;
use App\Http\Integrations\Sunny\SunnySync;
use App\Http\Integrations\Sunny\SunnyWrites;
use App\Models\Item;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Http\UploadedFile;
use Native\Mobile\Testing\Native;
use Saloon\Config;
use Saloon\Enums\Method;
use Saloon\Http\Faking\MockResponse;

beforeEach(function (): void {
    config(['services.sunny.api_url' => 'https://sunny.example/api']);
    Config::preventStrayRequests();
    Native::fakeBridge()->respondTo('SecureStorage.Get', ['value' => 'saved-token']);
    seedSunnyData();
});

it('creates and edits recipes and items then stores the confirmed server response', function (string $resource, string $route, string $ref, bool $editing): void {
    $model = $resource === 'recipes' ? Recipe::class : Item::class;
    $record = $model::find(1)->toArray();
    $record['id'] = $editing ? 1 : 90;
    $record['name'] = 'Saved by Sunny';
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record], $editing ? 200 : 201)]);
    Native::visit('/'.$route.($editing ? '/1/edit' : '/create'))
        ->set('name', 'Draft name')->tap($ref.'-submit')
        ->assertSet('error', '')->assertReplacedWith('/'.$route.'/'.$record['id']);
    expect($model::find($record['id'])->name)->toBe('Saved by Sunny');
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->resolveEndpoint() === '/teams/family/'.$resource.($editing ? '/1' : '')
        && $request->getMethod() === ($editing ? Method::PATCH : Method::POST)
        && $request->body()->get('name') === 'Draft name');
})->with([
    ['recipes', 'recipes', 'create-recipe', false], ['recipes', 'recipes', 'edit-recipe', true],
    ['items', 'inventory', 'create-item', false], ['items', 'inventory', 'edit-item', true],
]);

it('sends the entire recipe form and preserves unedited rich text', function (): void {
    Recipe::find(1)->update(['ingredients' => '<p><strong>Flour</strong></p>']);
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => Recipe::find(1)->toArray()])]);
    Native::visit('/recipes/1/edit')->set('tags', ['Vegan'])->set('nutrition', 'Protein: 2g')->set('notes', '')
        ->set('instructions', 'Mix')->tap('edit-recipe-submit')->assertSet('error', '');
    Saloon::assertSent(function (SaveRecordRequest $request): bool {
        $body = $request->body()->all();
        expect($body)->not->toHaveKey('ingredients');
        expect($body['tags'])->toBe(['Vegan']);
        expect($body['nutrition'])->toBe('Protein: 2g');
        expect($body['notes'])->toBeNull();
        expect($body['instructions'])->toBe('<ol><li>Mix</li></ol>');

        return true;
    });
});

it('uploads a photo with method spoofing and retains metadata keys', function (): void {
    $photo = UploadedFile::fake()->image('photo.jpg');
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => Item::find(1)->toArray()])]);
    Native::visit('/inventory/1/edit')->set('photoPath', $photo->getPathname())
        ->set('metadata', [['key' => 'size[inches]', 'value' => '12']])
        ->tap('edit-item-submit')->assertSet('error', '');
    Saloon::assertSent(function (SaveRecordRequest $request) use ($photo): bool {
        expect($request->getMethod())->toBe(Method::POST);
        expect($request->body()->get('_method')->value)->toBe('PATCH');
        expect($request->body()->get('remove_photo')->value)->toBe('0');
        expect(json_decode($request->body()->get('metadata')->value, true))->toBe(['size[inches]' => '12']);
        expect((string) $request->body()->get('photo')->value)->toBe(file_get_contents($photo->getPathname()));

        return true;
    });
});

it('removes an existing recipe photo explicitly', function (): void {
    Recipe::find(1)->update(['photo_url' => 'https://sunny.example/photo.jpg']);
    $record = Recipe::find(1)->toArray();
    $record['photo_url'] = null;
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record])]);
    Native::visit('/recipes/1/edit')->tap('edit-recipe-photo-remove')->tap('edit-recipe-submit')->assertSet('error', '');
    Saloon::assertSent(fn (SaveRecordRequest $request): bool => $request->body()->get('remove_photo') === true);
    expect(Recipe::find(1)->photo_url)->toBeNull();
});

it('keeps rejected changes on screen and leaves SQLite untouched', function (int $status, string $message): void {
    $name = Recipe::find(1)->name;
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['errors' => ['name' => ['That name is taken.']]], $status)]);
    Native::visit('/recipes/1/edit')->set('name', 'Unsaved')->tap('edit-recipe-submit')
        ->assertNoNavigation()->assertSet('name', 'Unsaved')->assertSet('error', $message);
    expect(Recipe::find(1)->name)->toBe($name);
})->with([
    [422, 'That name is taken.'], [403, 'You no longer have permission to save to this team.'],
    [500, 'Unable to confirm the save. Sync with Sunny before retrying to avoid duplicates.'],
]);

it('uses the active team and filters parents by team', function (): void {
    Team::create(['id' => 2, 'name' => 'Work', 'slug' => 'work', 'server' => SunnyStore::server()]);
    Item::create(['id' => 99, 'team_id' => 2, 'name' => 'Work bin', 'type' => 'bin', 'server' => SunnyStore::server()]);
    Native::visit('/dashboard')->tap('active-team')->tap('team-2');
    Native::visit('/inventory/create')->assertSet('teamId', 2)->assertSet('parentOptions', ['Top level', 'Work bin']);
});

it('does not resubmit a confirmed create if storing the local copy fails', function (): void {
    $record = Recipe::find(1)->toArray();
    $record['id'] = 90;
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record], 201)]);
    $this->mock(SunnyStore::class)->shouldReceive('saveRecord')->once()->andThrow(new RuntimeException('Disk full'));
    Native::visit('/recipes/create')->set('name', 'Soup')->call('save')->assertSet('savedId', 90)
        ->assertSee('Saved on Sunny, but the local copy could not be updated. Go back and sync to see it.')
        ->call('save');
    Saloon::assertSentCount(1);
});

it('keeps the draft when the network fails without changing local records', function (): void {
    $this->mock(SunnyWrites::class)->shouldReceive('save')->once()->andThrow(new RuntimeException('Connection lost'));
    Native::visit('/recipes/create')->set('name', 'Offline draft')->tap('create-recipe-submit')
        ->assertNoNavigation()->assertSet('name', 'Offline draft')
        ->assertSee('Unable to confirm the save. Check your connection and sync before retrying to avoid duplicates.');
    expect(Recipe::count())->toBe(6);
});

it('rejects a missing photo before sending a request', function (): void {
    Saloon::fake([]);
    Native::visit('/recipes/create')->set('name', 'Cake')->set('photoPath', '/missing-sunny-photo.jpg')
        ->tap('create-recipe-submit')->assertNoNavigation()
        ->assertSee('Choose the photo again; its file is no longer available.');
    Saloon::assertNothingSent();
});

it('rejects responses for another team without writing them locally', function (): void {
    $record = Recipe::find(1)->toArray();
    $record['team_id'] = 999;
    Saloon::fake([SaveRecordRequest::class => MockResponse::make(['data' => $record])]);
    Native::visit('/recipes/1/edit')->set('name', 'Draft')->tap('edit-recipe-submit')->assertNoNavigation();
    expect(Recipe::find(1)->team_id)->toBe(1)->and(Recipe::find(1)->name)->not->toBe('Draft');
});

it('syncs team route keys and remote photo URLs for subsequent edits', function (): void {
    $snapshot = [
        'teams' => [['id' => 1, 'name' => 'Family', 'slug' => 'family-home']],
        'recipes' => [Recipe::find(1)->toArray()], 'items' => [], 'synced_at' => now()->toIso8601String(),
    ];
    $snapshot['recipes'][0]['photo_url'] = 'https://sunny.example/photo.jpg';
    Saloon::fake([SyncRequest::class => MockResponse::make($snapshot)]);
    app(SunnySync::class)->sync();
    expect(Team::find(1)->slug)->toBe('family-home');
    Native::visit('/recipes/1/edit')->assertSet('existingPhotoUrl', 'https://sunny.example/photo.jpg');
});
