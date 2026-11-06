<?php

use App\Actions\MealPlanning\OverrideMealSlot;
use App\Actions\MealPlanning\ResolveMealPlan;
use App\Actions\MealPlanning\SaveMealPlanHistory;
use App\Models\MealPlanSlot;
use App\Models\MealTemplateItem;
use App\Models\Team;
use Carbon\Carbon;
use Illuminate\Support\Facades\Date;

use function Pest\Laravel\travelTo;

function mealsOn(Team $team, string $date, string $slot): array
{
    $day = (new ResolveMealPlan)->handle($team->fresh(), Date::parse($date), Date::parse($date))->first();

    return $day['slots']->firstWhere('slot.name', $slot)['items']->map->label()->all();
}

beforeEach(function () {
    // Thursday Nov 5 is the last saved day. It's 03:00 UTC on Tuesday Nov 10,
    // which is still Monday Nov 9 evening in Chicago.
    travelTo(Date::parse('2026-11-10 03:00:00', 'UTC'));

    $this->team = Team::factory()->create(['timezone' => 'America/Chicago', 'meals_saved_through' => '2026-11-05']);
    $this->breakfast = $this->team->mealSlots()->firstWhere('name', 'Breakfast');
    $this->dinner = $this->team->mealSlots()->firstWhere('name', 'Dinner');

    foreach (range(0, 6) as $weekday) {
        MealTemplateItem::factory()->for($this->breakfast, 'slot')->create(['weekday' => $weekday, 'name' => 'Oatmeal']);
        MealTemplateItem::factory()->for($this->dinner, 'slot')->create(['weekday' => $weekday, 'name' => 'Tacos']);
    }
});

test('new teams start with breakfast, lunch, and dinner', function () {
    expect(Team::factory()->create()->mealSlots()->pluck('name')->all())
        ->toBe(['Breakfast', 'Lunch', 'Dinner']);
});

test('it saves every day that has ended in the team timezone, catching up missed days', function () {
    $saved = resolve(SaveMealPlanHistory::class)->handle($this->team);

    expect($saved)->toBe(3)
        ->and($this->team->fresh()->meals_saved_through)->toBe('2026-11-08')
        ->and(MealPlanSlot::pluck('date')->map->toDateString()->unique()->sort()->values()->all())
        ->toBe(['2026-11-06', '2026-11-07', '2026-11-08']);
});

test('saved days keep their meals when the template changes', function () {
    resolve(SaveMealPlanHistory::class)->handle($this->team);

    MealTemplateItem::query()->where('name', 'Tacos')->update(['name' => 'Curry']);

    expect(mealsOn($this->team, '2026-11-08', 'Dinner'))->toBe(['Tacos'])
        ->and(mealsOn($this->team, '2026-11-09', 'Dinner'))->toBe(['Curry']);
});

test('an overridden slot is saved as it was changed, and other slots that day still come from the template', function () {
    $override = (new OverrideMealSlot)->handle($this->dinner, Date::parse('2026-11-07'));
    $override->items()->delete();
    $override->items()->create(['name' => 'Pizza night']);

    resolve(SaveMealPlanHistory::class)->handle($this->team);

    expect(mealsOn($this->team, '2026-11-07', 'Dinner'))->toBe(['Pizza night'])
        ->and(mealsOn($this->team, '2026-11-07', 'Breakfast'))->toBe(['Oatmeal']);
});

test('running it again saves nothing new', function () {
    $save = resolve(SaveMealPlanHistory::class);
    $save->handle($this->team);
    $rows = MealPlanSlot::count();

    expect($save->handle($this->team->fresh()))->toBe(0)
        ->and(MealPlanSlot::count())->toBe($rows);
});

test('days before saved history began do not show the template', function () {
    expect(mealsOn($this->team, '2026-11-01', 'Dinner'))->toBe([])
        ->and(mealsOn($this->team, '2026-11-12', 'Dinner'))->toBe(['Tacos']);
});

test('the week starts on the team first day of the week', function () {
    $this->team->update(['week_start' => Carbon::MONDAY]);

    [$start, $end] = ResolveMealPlan::week($this->team, Date::parse('2026-11-08')); // Sunday

    expect($start->toDateString())->toBe('2026-11-02')
        ->and($end->toDateString())->toBe('2026-11-08');
});

test('the last saved day shows only what was saved when the week is read in the team timezone', function () {
    $this->team->update(['meals_saved_through' => '2026-11-08']);
    $team = $this->team->fresh();

    $days = (new ResolveMealPlan)->handle($team, ...ResolveMealPlan::week($team, $team->today()))
        ->mapWithKeys(fn (array $day) => [$day['date']->toDateString() => $day['slots']->firstWhere('slot.name', 'Dinner')['items']->map->label()->all()]);

    expect($days['2026-11-08'])->toBe([])
        ->and($days['2026-11-09'])->toBe(['Tacos']);
});

test('teams ahead of UTC still save every day that has ended', function () {
    travelTo(Date::parse('2026-11-09 16:00:00', 'UTC')); // 01:00 on Nov 10 in Tokyo

    $team = Team::factory()->create(['timezone' => 'Asia/Tokyo', 'meals_saved_through' => '2026-11-07']);

    expect(resolve(SaveMealPlanHistory::class)->handle($team))->toBe(2)
        ->and($team->fresh()->meals_saved_through)->toBe('2026-11-09');
});
