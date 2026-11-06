<?php

use App\Enums\ChecklistType;
use App\Models\Checklist;
use App\Models\MealPlanSlot;
use App\Models\MealTemplateItem;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Support\Facades\Date;
use Livewire\Livewire;

use function Pest\Laravel\actingAs;
use function Pest\Laravel\travelTo;

beforeEach(function () {
    travelTo(Date::parse('2026-11-04 12:00:00', 'America/Chicago')); // Wednesday

    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->team->update(['meals_saved_through' => '2026-11-03']);
    $this->breakfast = $this->team->mealSlots()->firstWhere('name', 'Breakfast');
    $this->dinner = $this->team->mealSlots()->firstWhere('name', 'Dinner');

    // Thursday (weekday 4) template: oatmeal, then tacos with rice.
    MealTemplateItem::factory()->for($this->breakfast, 'slot')->create(['weekday' => 4, 'name' => 'Oatmeal']);
    MealTemplateItem::factory()->for($this->dinner, 'slot')->create(['weekday' => 4, 'name' => 'Tacos']);
    MealTemplateItem::factory()->for($this->dinner, 'slot')->create(['weekday' => 4, 'name' => 'Rice']);
});

$cell = function ($component, string $date, string $slot): array {
    $day = $component->instance()->days->first(fn (array $day) => $day['date']->toDateString() === $date);
    $match = $day['slots']->firstWhere('slot.name', $slot);

    return ['items' => $match['items']->map->label()->all(), 'overridden' => $match['overridden']];
};

test('renders successfully', function () {
    actingAs($this->user)
        ->get(route('meal-plan.index'))
        ->assertOk()
        ->assertSee('Tacos');

    Livewire::actingAs($this->user)
        ->test('pages::meal-plan.index')
        ->assertOk();
})->group('smoke');

test('adding a meal on a date changes only that slot on that date', function () use ($cell) {
    $component = Livewire::actingAs($this->user)
        ->test('pages::meal-plan.index')
        ->call('startAdding', $this->dinner->id, '2026-11-05')
        ->set('meal', 'Salad')
        ->call('addMeal')
        ->assertHasNoErrors();

    expect($cell($component, '2026-11-05', 'Dinner'))->toBe(['items' => ['Tacos', 'Rice', 'Salad'], 'overridden' => true])
        ->and($cell($component, '2026-11-05', 'Breakfast'))->toBe(['items' => ['Oatmeal'], 'overridden' => false])
        ->and(MealTemplateItem::where('weekday', 4)->pluck('name')->all())->toBe(['Oatmeal', 'Tacos', 'Rice']);

    $component->call('nextWeek');

    expect($cell($component, '2026-11-12', 'Dinner')['items'])->toBe(['Tacos', 'Rice']);
});

test('removing every meal from a slot skips it instead of falling back to the template', function () use ($cell) {
    $component = Livewire::actingAs($this->user)
        ->test('pages::meal-plan.index')
        ->call('removeMeal', $this->dinner->id, '2026-11-05', 0)
        ->call('removeMeal', $this->dinner->id, '2026-11-05', 0);

    expect($cell($component, '2026-11-05', 'Dinner'))->toBe(['items' => [], 'overridden' => true]);

    $component->call('resetSlot', $this->dinner->id, '2026-11-05');

    expect($cell($component, '2026-11-05', 'Dinner'))->toBe(['items' => ['Tacos', 'Rice'], 'overridden' => false]);
});

test('moving a meal reorders it within its slot', function () use ($cell) {
    $component = Livewire::actingAs($this->user)
        ->test('pages::meal-plan.index')
        ->call('moveMeal', $this->dinner->id, '2026-11-05', 1, -1);

    expect($cell($component, '2026-11-05', 'Dinner')['items'])->toBe(['Rice', 'Tacos']);
});

test('saved past days cannot be reset to the template', function () use ($cell) {
    MealPlanSlot::factory()->for($this->dinner, 'slot')->create(['date' => '2026-11-03'])
        ->items()->create(['name' => 'Soup']);

    $component = Livewire::actingAs($this->user)
        ->test('pages::meal-plan.index')
        ->call('resetSlot', $this->dinner->id, '2026-11-03');

    expect($cell($component, '2026-11-03', 'Dinner'))->toBe(['items' => ['Soup'], 'overridden' => false]);
});

test('typing a recipe name links the recipe, and anything else is free text', function () {
    $recipe = Recipe::factory()->for($this->team)->create(['name' => 'Chicken Curry']);

    Livewire::actingAs($this->user)
        ->test('pages::meal-plan.index')
        ->call('startAdding', $this->breakfast->id, '2026-11-06')
        ->set('meal', 'chicken curry')
        ->call('addMeal')
        ->call('startAdding', $this->breakfast->id, '2026-11-06')
        ->set('meal', 'Leftovers')
        ->call('addMeal');

    $items = MealPlanSlot::where('date', '2026-11-06')->sole()->items;

    expect($items->pluck('recipe_id')->all())->toBe([$recipe->id, null])
        ->and($items->pluck('name')->all())->toBe(['Chicken Curry', 'Leftovers']);
});

test('another team\'s recipe is not linked', function () {
    Recipe::factory()->create(['name' => 'Chicken Curry']);

    Livewire::actingAs($this->user)
        ->test('pages::meal-plan.index')
        ->call('startAdding', $this->breakfast->id, '2026-11-06')
        ->set('meal', 'Chicken Curry')
        ->call('addMeal');

    expect(MealPlanSlot::where('date', '2026-11-06')->sole()->items->sole()->recipe_id)->toBeNull();
});

describe('authorization', function () {
    test('another team\'s slot cannot be edited', function () {
        $otherSlot = User::factory()->create()->currentTeam->mealSlots()->first();

        Livewire::actingAs($this->user)
            ->test('pages::meal-plan.index')
            ->call('removeMeal', $otherSlot->id, '2026-11-05', 0)
            ->assertNotFound();

        expect(MealPlanSlot::count())->toBe(0);
    });
});

describe('validation', function () {
    test('a meal is required and fits the column', function (string $meal, string $rule) {
        Livewire::actingAs($this->user)
            ->test('pages::meal-plan.index')
            ->call('startAdding', $this->dinner->id, '2026-11-05')
            ->set('meal', $meal)
            ->call('addMeal')
            ->assertHasErrors(['meal' => $rule]);

        expect(MealPlanSlot::count())->toBe(0);
    })->with([
        'blank' => ['   ', 'required'],
        'too long' => [str_repeat('a', 256), 'max'],
    ]);

    test('a malformed date is rejected', function () {
        Livewire::actingAs($this->user)
            ->test('pages::meal-plan.index')
            ->call('removeMeal', $this->dinner->id, 'next tuesday', 0)
            ->assertNotFound();
    });
});

describe('shopping list', function () {
    beforeEach(function () {
        $this->tacos = Recipe::factory()->for($this->team)->create([
            'name' => 'Tacos',
            'ingredients' => '<ul><li>Tortillas</li><li>Beef</li><li>Cheese</li></ul>',
        ]);
        MealTemplateItem::query()->where('name', 'Tacos')->update(['recipe_id' => $this->tacos->id]);
        $this->list = Checklist::factory()->for($this->team)->create(['type' => ChecklistType::Shopping, 'name' => 'Groceries']);
    });

    test('ticked ingredient lines are added to the chosen list', function () {
        $component = Livewire::actingAs($this->user)
            ->test('pages::meal-plan.index')
            ->call('startShopping');

        expect($component->get('shoppingFrom'))->toBe('2026-11-04')
            ->and($component->get('selectedLines'))->toBe(["{$this->tacos->id}:0", "{$this->tacos->id}:1", "{$this->tacos->id}:2"]);

        $component
            ->set('shoppingListId', $this->list->id)
            ->set('selectedLines', ["{$this->tacos->id}:0", "{$this->tacos->id}:2"])
            ->call('addToShoppingList')
            ->assertHasNoErrors();

        expect($this->list->items()->pluck('name')->all())->toBe(['Tortillas', 'Cheese']);
    });

    test('only days in the chosen range are included', function () {
        Livewire::actingAs($this->user)
            ->test('pages::meal-plan.index')
            ->call('startShopping')
            ->set('shoppingFrom', '2026-11-06')
            ->assertSet('selectedLines', []);
    });

    test('another team\'s list cannot be chosen', function () {
        $otherList = Checklist::factory()->create(['type' => ChecklistType::Shopping]);

        Livewire::actingAs($this->user)
            ->test('pages::meal-plan.index')
            ->call('startShopping')
            ->set('shoppingListId', $otherList->id)
            ->call('addToShoppingList')
            ->assertHasErrors('shoppingListId');

        expect($otherList->items()->count())->toBe(0);
    });
});
