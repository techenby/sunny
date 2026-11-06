<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\MealPlanning\SetMealPlanSlot;
use App\Models\MealPlanSlot;
use App\Models\MealTemplateItem;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'America/Chicago'));

    $this->user = User::factory()->create();
    $this->team = $this->user->currentTeam;
    $this->dinner = $this->team->mealSlots()->firstWhere('name', 'Dinner');
    $this->breakfast = $this->team->mealSlots()->firstWhere('name', 'Breakfast');
    MealTemplateItem::factory()->for($this->dinner, 'slot')->create(['weekday' => 4, 'name' => 'Tacos']);
    MealTemplateItem::factory()->for($this->breakfast, 'slot')->create(['weekday' => 4, 'name' => 'Oatmeal']);
});

test('it replaces one slot on one date, linking recipes and keeping free text', function () {
    $curry = Recipe::factory()->for($this->team)->create(['name' => 'Chicken Curry']);

    SunnyServer::actingAs($this->user)
        ->tool(SetMealPlanSlot::class, [
            'date' => '2026-11-05',
            'meal_slot_id' => $this->dinner->id,
            'items' => [['recipe_id' => $curry->id, 'name' => 'ignored'], ['name' => 'Garlic naan']],
        ])
        ->assertOk()
        ->assertStructuredContent([
            'date' => '2026-11-05',
            'meal_slot_id' => $this->dinner->id,
            'slot_name' => 'Dinner',
            'items' => [
                ['recipe_id' => $curry->id, 'name' => 'Chicken Curry'],
                ['recipe_id' => null, 'name' => 'Garlic naan'],
            ],
        ]);

    expect(MealPlanSlot::pluck('meal_slot_id')->all())->toBe([$this->dinner->id])
        ->and(MealTemplateItem::orderBy('id')->pluck('name')->all())->toBe(['Tacos', 'Oatmeal']);
});

test('an empty list skips the meal on that date', function () {
    SunnyServer::actingAs($this->user)
        ->tool(SetMealPlanSlot::class, ['date' => '2026-11-05', 'meal_slot_id' => $this->dinner->id, 'items' => []])
        ->assertOk();

    expect(MealPlanSlot::sole()->items()->count())->toBe(0);
});

test('it will not link another team\'s recipe or use another team\'s slot', function () {
    $other = Recipe::factory()->create();

    SunnyServer::actingAs($this->user)
        ->tool(SetMealPlanSlot::class, ['date' => '2026-11-05', 'meal_slot_id' => $this->dinner->id, 'items' => [['recipe_id' => $other->id]]])
        ->assertHasErrors(["Recipe {$other->id} was not found on the current team. Use the search-recipes tool to find ids."]);

    SunnyServer::actingAs($this->user)
        ->tool(SetMealPlanSlot::class, ['date' => '2026-11-05', 'meal_slot_id' => User::factory()->create()->currentTeam->mealSlots()->first()->id, 'items' => []])
        ->assertHasErrors(['Meal slot not found. Use get-meal-plan or get-meal-template to find meal_slot_id values.']);

    expect(MealPlanSlot::count())->toBe(0);
});

test('each meal needs a recipe or a name', function () {
    SunnyServer::actingAs($this->user)
        ->tool(SetMealPlanSlot::class, ['date' => '2026-11-05', 'meal_slot_id' => $this->dinner->id, 'items' => [['name' => '']]])
        ->assertHasErrors(['Each meal needs a recipe_id or a name.']);
});
