<?php

use App\Actions\Onboarding\SetUpHome;
use App\Enums\ChecklistType;
use App\Enums\ItemType;
use App\Enums\RoutineFrequency;
use App\Enums\TimeOfDay;
use App\Models\User;
use Illuminate\Support\Facades\Http;

test('it creates the places that were picked', function () {
    $team = User::factory()->create()->currentTeam;

    resolve(SetUpHome::class)->handle($team, ['Garage', 'Hall closet', ' garage ', 'Under the stairs', ''], []);

    expect($team->items()->orderBy('id')->pluck('name')->all())->toBe(['Garage', 'Hall closet', 'Under the stairs'])
        ->and($team->items()->where('type', '!=', ItemType::Location)->exists())->toBeFalse()
        ->and($team->items()->whereNotNull('parent_id')->exists())->toBeFalse();
});

test('it creates a shared groceries list with staples', function () {
    $team = User::factory()->create()->currentTeam;

    resolve(SetUpHome::class)->handle($team, [], ['groceries']);

    $list = $team->checklists()->sole();

    expect($list->name)->toBe('Groceries')
        ->and($list->type)->toBe(ChecklistType::Shopping)
        ->and($list->user_id)->toBeNull()
        ->and($list->items()->orderBy('position')->pluck('name')->all())->toBe(SetUpHome::GROCERIES);
});

test('it creates household bedtime and morning routines', function () {
    $team = User::factory()->create()->currentTeam;

    resolve(SetUpHome::class)->handle($team, [], ['bedtime', 'morning']);

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
    $team = User::factory()->create()->currentTeam;

    resolve(SetUpHome::class)->handle($team, [], ['recipe']);

    $recipe = $team->recipes()->sole();

    expect($recipe->name)->toBe('Apple Cider Donuts')
        ->and($recipe->source)->toBe(SetUpHome::SAMPLE_RECIPE_URL)
        ->and($recipe->servings)->toBe('12')
        ->and($recipe->ingredients)->toHaveCount(2)
        ->and($recipe->instructions)->toHaveCount(1);
});

test('it skips the sample recipe when the import fails, and still sets up the rest', function () {
    Http::fake(['safelydelish.com/*' => Http::response('Down for maintenance', 503)]);
    $team = User::factory()->create()->currentTeam;

    resolve(SetUpHome::class)->handle($team, ['Garage'], ['groceries', 'recipe']);

    expect($team->recipes()->exists())->toBeFalse()
        ->and($team->items()->count())->toBe(1)
        ->and($team->checklists()->count())->toBe(1);
});

test('it creates nothing when nothing was picked', function () {
    Http::fake();
    $team = User::factory()->create()->currentTeam;

    resolve(SetUpHome::class)->handle($team, [], []);

    expect($team->items()->exists())->toBeFalse()
        ->and($team->checklists()->exists())->toBeFalse()
        ->and($team->routines()->exists())->toBeFalse()
        ->and($team->recipes()->exists())->toBeFalse();

    Http::assertNothingSent();
});
