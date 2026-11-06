<?php

use App\Mcp\Tools\MealPlanning\GetMealTemplate;
use App\Mcp\Tools\MealPlanning\SetMealTemplateSlot;
use App\Models\MealTemplateItem;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Testing\Fluent\AssertableJson;
use Tests\Feature\Mcp\SunnyTestServer;

test('setting a template slot replaces only that weekday, and the template lists weekdays in the team order', function () {
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['week_start' => Carbon::MONDAY]);
    $dinner = $team->mealSlots()->firstWhere('name', 'Dinner');
    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 2, 'name' => 'Old tacos']);
    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 3, 'name' => 'Curry']);

    SunnyTestServer::actingAs($user)
        ->tool(SetMealTemplateSlot::class, ['weekday' => 2, 'meal_slot_id' => $dinner->id, 'items' => [['name' => 'Tacos'], ['name' => 'Rice']]])
        ->assertOk()
        ->assertStructuredContent([
            'weekday' => 2,
            'meal_slot_id' => $dinner->id,
            'slot_name' => 'Dinner',
            'items' => [['recipe_id' => null, 'name' => 'Tacos'], ['recipe_id' => null, 'name' => 'Rice']],
        ]);

    SunnyTestServer::actingAs($user)
        ->tool(GetMealTemplate::class)
        ->assertOk()
        ->assertStructuredContent(fn (AssertableJson $json) => $json
            ->where('slots.2', ['id' => $dinner->id, 'name' => 'Dinner'])
            ->where('weekdays.0.weekday', 1)
            ->where('weekdays.0.name', 'Monday')
            ->where('weekdays.6.weekday', 0)
            ->where('weekdays.1.slots.2.items', [['recipe_id' => null, 'name' => 'Tacos'], ['recipe_id' => null, 'name' => 'Rice']])
            ->where('weekdays.2.slots.2.items', [['recipe_id' => null, 'name' => 'Curry']])
            ->etc());
});

test('the weekday must be within the week', function () {
    $user = User::factory()->create();

    SunnyTestServer::actingAs($user)
        ->tool(SetMealTemplateSlot::class, ['weekday' => 7, 'meal_slot_id' => $user->currentTeam->mealSlots()->first()->id, 'items' => []])
        ->assertHasErrors(['The weekday must be an integer from 0 (Sunday) to 6 (Saturday).']);
});
