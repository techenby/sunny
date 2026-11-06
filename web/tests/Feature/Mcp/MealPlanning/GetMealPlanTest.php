<?php

use App\Mcp\Servers\SunnyServer;
use App\Mcp\Tools\MealPlanning\GetMealPlan;
use App\Models\MealPlanSlot;
use App\Models\MealTemplateItem;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Testing\Fluent\AssertableJson;

beforeEach(function () {
    // 03:00 UTC on Thursday is still Wednesday Nov 4 in Chicago.
    $this->travelTo(CarbonImmutable::parse('2026-11-05 03:00', 'UTC'));
});

test('it resolves days from the template, per-date changes, and saved history in the team timezone', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['meals_saved_through' => '2026-11-03']);
    $dinner = $team->mealSlots()->firstWhere('name', 'Dinner');
    $curry = Recipe::factory()->for($team)->create(['name' => 'Curry']);

    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 2, 'name' => 'Template Tuesday']);
    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 3, 'recipe_id' => $curry->id, 'name' => 'Curry']);
    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 4, 'name' => 'Tacos']);
    MealPlanSlot::factory()->for($dinner, 'slot')->create(['date' => '2026-11-03'])->items()->create(['name' => 'Saved soup']);
    MealPlanSlot::factory()->for($dinner, 'slot')->create(['date' => '2026-11-05'])->items()->create(['name' => 'Pizza night']);
    MealTemplateItem::factory()->for(User::factory()->create()->currentTeam->mealSlots()->first(), 'slot')->create(['weekday' => 3, 'name' => 'Not ours']);

    SunnyServer::actingAs($user)
        ->tool(GetMealPlan::class, ['from' => '2026-11-03', 'days' => 3])
        ->assertOk()
        ->assertDontSee(['Template Tuesday', 'Not ours'])
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('timezone', 'America/Chicago')
            ->has('days', 3)
            ->where('days.0.date', '2026-11-03')
            ->where('days.0.saved', true)
            ->where('days.0.slots.2.items', [['recipe_id' => null, 'name' => 'Saved soup']])
            ->where('days.1.weekday', 'Wednesday')
            ->where('days.1.saved', false)
            ->where('days.1.slots.0.slot_name', 'Breakfast')
            ->where('days.1.slots.2', [
                'meal_slot_id' => $dinner->id,
                'slot_name' => 'Dinner',
                'overridden' => false,
                'items' => [['recipe_id' => $curry->id, 'name' => 'Curry']],
            ])
            ->where('days.2.slots.2.overridden', true)
            ->where('days.2.slots.2.items', [['recipe_id' => null, 'name' => 'Pizza night']]));
});

test('it starts today in the team timezone and limits the range', function () {
    $user = User::factory()->create();

    SunnyServer::actingAs($user)
        ->tool(GetMealPlan::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('days.0.date', '2026-11-04')
            ->where('days.6.date', '2026-11-10')
            ->etc());

    SunnyServer::actingAs($user)
        ->tool(GetMealPlan::class, ['days' => 32])
        ->assertHasErrors(['The meal plan can be read at most 31 days at a time.']);
});
