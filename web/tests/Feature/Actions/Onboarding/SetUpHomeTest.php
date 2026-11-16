<?php

use App\Actions\Onboarding\SetUpHome;
use App\Actions\Recipes\CreateRecipe;
use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\User;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Http;

test('it creates the places that were picked', function () {
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    resolve(SetUpHome::class)->handle($user, $team, ['Garage', 'Hall closet', ' garage ', 'Under the stairs', ''], []);

    expect($team->items()->orderBy('id')->pluck('name')->all())->toBe(['Garage', 'Hall closet', 'Under the stairs'])
        ->and($team->items()->where('type', '!=', ItemType::Location)->exists())->toBeFalse()
        ->and($team->items()->whereNotNull('parent_id')->exists())->toBeFalse();
});

test('it creates a shared groceries list with staples', function () {
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    resolve(SetUpHome::class)->handle($user, $team, [], ['groceries']);

    $list = $team->checklists()->sole();

    expect($list->name)->toBe('Groceries')
        ->and($list->type)->toBe(ChecklistType::Shopping)
        ->and($list->user_id)->toBeNull()
        ->and($list->items()->orderBy('position')->pluck('name')->all())->toBe(SetUpHome::GROCERIES);
});

test('it creates household bedtime and morning routines', function () {
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    resolve(SetUpHome::class)->handle($user, $team, [], ['bedtime', 'morning']);

    $bedtime = $team->routines()->where('name', 'Bedtime')->sole();
    $morning = $team->routines()->where('name', 'Morning')->sole();

    expect($bedtime->user_id)->toBeNull()
        ->and($bedtime->time_of_day)->toBe(TimeOfDay::Evening)
        ->and($bedtime->frequency)->toBe(RoutineFrequency::Daily)
        ->and($bedtime->is_active)->toBeTrue()
        ->and($bedtime->starts_on->toDateString())->toBe($team->today()->toDateString())
        ->and($bedtime->steps->pluck('name')->all())->toBe(SetUpHome::BEDTIME)
        ->and($morning->time_of_day)->toBe(TimeOfDay::Morning)
        ->and($morning->frequency)->toBe(RoutineFrequency::Weekly)
        ->and($morning->scheduledWeekdays())->toBe([1, 2, 3, 4, 5])
        ->and($morning->steps->pluck('name')->all())->toBe(SetUpHome::MORNING);
});

test('it imports the sample recipe and keeps its source', function () {
    fakeSampleRecipe();
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    resolve(SetUpHome::class)->handle($user, $team, [], ['recipe']);

    $recipe = $team->recipes()->sole();

    expect($recipe->name)->toBe('Apple Cider Donuts')
        ->and($recipe->source)->toBe(SetUpHome::SAMPLE_RECIPE_URL)
        ->and($recipe->servings)->toBe('12')
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->instructions)->toHaveCount(1);
});

test('it skips the sample recipe when the import fails, and still sets up the rest', function () {
    Http::fake(['safelydelish.com/*' => Http::response('Down for maintenance', 503)]);
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    resolve(SetUpHome::class)->handle($user, $team, ['Garage'], ['groceries', 'recipe']);

    expect($team->recipes()->exists())->toBeFalse()
        ->and($team->items()->count())->toBe(1)
        ->and($team->checklists()->count())->toBe(1);
});

test('it creates nothing when nothing was picked', function () {
    Http::fake();
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    resolve(SetUpHome::class)->handle($user, $team, [], []);

    expect($team->items()->exists())->toBeFalse()
        ->and($team->checklists()->exists())->toBeFalse()
        ->and($team->routines()->exists())->toBeFalse()
        ->and($team->recipes()->exists())->toBeFalse();

    Http::assertNothingSent();
});

test('it marks the user as onboarded along with what it creates', function () {
    $user = User::factory()->needsOnboarding()->create();

    $didSetUp = resolve(SetUpHome::class)->handle($user, $user->currentTeam, ['Garage'], ['groceries']);

    expect($didSetUp)->toBeTrue()
        ->and($user->fresh()->needsOnboarding())->toBeFalse();
});

test('running it again creates nothing more', function () {
    fakeSampleRecipe();
    $user = User::factory()->needsOnboarding()->create();
    $team = $user->currentTeam;

    resolve(SetUpHome::class)->handle($user, $team, ['Garage'], SetUpHome::STARTERS);
    $didSetUp = resolve(SetUpHome::class)->handle($user, $team, ['Garage'], SetUpHome::STARTERS);

    expect($didSetUp)->toBeFalse()
        ->and($team->items()->count())->toBe(1)
        ->and($team->checklists()->count())->toBe(1)
        ->and($team->routines()->count())->toBe(2)
        ->and($team->recipes()->count())->toBe(1);

    Http::assertSentCount(1);
});

test('it creates nothing when another request already finished set-up', function () {
    Http::fake();
    $user = User::factory()->needsOnboarding()->create();
    User::whereKey($user->id)->update(['onboarded_at' => now()]);

    $didSetUp = resolve(SetUpHome::class)->handle($user, $user->currentTeam, ['Garage'], SetUpHome::STARTERS);

    expect($didSetUp)->toBeFalse()
        ->and($user->currentTeam->items()->exists())->toBeFalse()
        ->and($user->currentTeam->checklists()->exists())->toBeFalse();

    Http::assertNothingSent();
});

test('a recipe that cannot be saved is reported and the rest of set-up stands', function () {
    Exceptions::fake();
    fakeSampleRecipe();
    $this->mock(CreateRecipe::class)->shouldReceive('handle')->andThrow(new RuntimeException('Recipe could not be saved'));
    $user = User::factory()->needsOnboarding()->create();

    $didSetUp = resolve(SetUpHome::class)->handle($user, $user->currentTeam, ['Garage'], ['groceries', 'recipe']);

    expect($didSetUp)->toBeTrue()
        ->and($user->fresh()->needsOnboarding())->toBeFalse()
        ->and($user->currentTeam->items()->count())->toBe(1)
        ->and($user->currentTeam->checklists()->count())->toBe(1);

    Exceptions::assertReported(fn (RuntimeException $e) => $e->getMessage() === 'Recipe could not be saved');
});
