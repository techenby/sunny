<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\Lists\AddChecklistItems;
use App\Models\Checklist;
use App\Models\ChecklistItem;
use App\Models\Item;
use App\Models\ItemPhoto;
use App\Models\Recipe;
use App\Models\Routine;
use App\Models\RoutineStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;

function fillToLimit(Model $model, int $limit): void
{
    $row = Arr::except($model->getAttributes(), ['id']);

    collect(range(2, $limit))
        ->map(fn (int $number): array => array_key_exists('slug', $row) ? [...$row, 'slug' => "{$row['slug']}-{$number}"] : $row)
        ->chunk(500)
        ->each(fn ($rows) => DB::table($model->getTable())->insert($rows->values()->all()));
}

test('a team, list or routine stops at the most it can hold', function (string $model, string $owner, string $message) {
    $first = $model::factory()->create();
    fillToLimit($first, $model::MAX_PER_OWNER);

    expect(fn () => $model::factory()->create([$owner => $first->getAttribute($owner)]))->toThrow(ValidationException::class, $message)
        ->and($model::query()->withoutGlobalScopes()->where($owner, $first->getAttribute($owner))->count())->toBe($model::MAX_PER_OWNER);

    expect($model::factory()->create())->exists->toBeTrue();
})->with([
    'items' => [Item::class, 'team_id', 'A team can have up to 5,000 items, counting recently deleted ones.'],
    'recipes' => [Recipe::class, 'team_id', 'A team can have up to 2,000 recipes, counting recently deleted ones.'],
    'lists' => [Checklist::class, 'team_id', 'A team can have up to 200 lists.'],
    'list items' => [ChecklistItem::class, 'checklist_id', 'A list can have up to 500 items.'],
    'routines' => [Routine::class, 'team_id', 'A team can have up to 100 routines.'],
    'routine steps' => [RoutineStep::class, 'routine_id', 'A routine can have up to 50 steps.'],
]);

test('deleted lists still count towards the most a team can hold', function () {
    $user = User::factory()->create();
    $first = Checklist::factory()->for($user->currentTeam)->create();
    fillToLimit($first, Checklist::MAX_PER_OWNER);
    $first->delete();

    $this->actingAs($user)
        ->postJson(route('api.checklists.store', $user->currentTeam), ['name' => 'One more', 'type' => 'shopping'])
        ->assertUnprocessable()
        ->assertJsonPath('errors.name.0', 'A team can have up to 200 lists.');
});

test('a team stops at the most item photos it can hold and keeps no stray file', function () {
    Storage::fake();
    $user = User::factory()->create();
    $item = Item::factory()->for($user->currentTeam)->create();
    $other = Item::factory()->for($user->currentTeam)->create();
    fillToLimit(ItemPhoto::factory()->for($other)->create(), ItemPhoto::MAX_PER_TEAM);

    $this->actingAs($user)
        ->postJson(route('api.items.photos.store', [$user->currentTeam, $item]), ['photo' => UploadedFile::fake()->image('hammer.jpg')])
        ->assertUnprocessable()
        ->assertJsonPath('errors.photo.0', 'A team can have up to 5,000 item photos.');

    expect($item->photos()->count())->toBe(0)
        ->and(Storage::allFiles())->toBe([]);
});

test('a recipe refuses text and lists that are too long', function (string $field, string|array $value) {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.recipes.store', $user->currentTeam), ['name' => 'Cake', $field => $value])
        ->assertUnprocessable()
        ->assertJsonValidationErrors($field);

    expect(Recipe::count())->toBe(0);
})->with([
    'description' => ['description', str_repeat('a', 20_001)],
    'notes' => ['notes', str_repeat('a', 20_001)],
    'nutrition' => ['nutrition', str_repeat('a', 5_001)],
    'ingredients as text' => ['ingredients', str_repeat('a', 20_001)],
    'ingredients as too many entries' => ['ingredients', array_fill(0, 501, '1 cup flour')],
    'instructions as oversized entries' => ['instructions', array_fill(0, 100, str_repeat('a', 1_000))],
    'tags' => ['tags', array_fill(0, 51, 'Dinner')],
]);

test('an item refuses details that are too large', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->postJson(route('api.items.store', $user->currentTeam), ['name' => 'Laptop', 'type' => 'item', 'metadata' => ['notes' => str_repeat('a', 10_001)]])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('metadata');

    expect(Item::count())->toBe(0);
});

test('the recipe form refuses tags that are not plain text', function () {
    Livewire::actingAs(User::factory()->create())
        ->test('pages::recipes.create')
        ->set('form.name', 'Cake')
        ->set('form.tags', [['nested']])
        ->call('save')
        ->assertHasErrors('form.tags.0');

    expect(Recipe::count())->toBe(0);
});

test('an assistant can add at most a hundred list items at once', function () {
    $user = User::factory()->create();
    $checklist = Checklist::factory()->for($user->currentTeam)->create();

    SunnyServer::actingAs($user)
        ->tool(AddChecklistItems::class, ['checklist_id' => $checklist->id, 'items' => array_fill(0, 101, 'Milk')])
        ->assertHasErrors();

    expect($checklist->items()->count())->toBe(0);
});
