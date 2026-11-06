<?php

use App\Mcp\Tools\MealPlanning\GetMealPlanIngredients;
use App\Models\MealTemplateItem;
use App\Models\Recipe;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Feature\Mcp\SunnyTestServer;

test('it groups ingredient lines by recipe across the chosen days', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'America/Chicago')); // Wednesday
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $dinner = $team->mealSlots()->firstWhere('name', 'Dinner');
    $tacos = Recipe::factory()->for($team)->create(['name' => 'Tacos', 'ingredients' => '<ul><li>Tortillas</li><li>Beef</li></ul>']);
    $soup = Recipe::factory()->for($team)->create(['name' => 'Soup', 'ingredients' => '<ul><li>Broth</li></ul>']);

    foreach ([3, 4] as $weekday) {
        MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => $weekday, 'recipe_id' => $tacos->id, 'name' => 'Tacos']);
    }
    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 5, 'name' => 'Leftovers']);
    MealTemplateItem::factory()->for($dinner, 'slot')->create(['weekday' => 6, 'recipe_id' => $soup->id, 'name' => 'Soup']); // Saturday, outside the range

    SunnyTestServer::actingAs($user)
        ->tool(GetMealPlanIngredients::class, ['days' => 3])
        ->assertOk()
        ->assertStructuredContent([
            'from' => '2026-11-04',
            'through' => '2026-11-06',
            'recipes' => [
                ['recipe_id' => $tacos->id, 'recipe_name' => 'Tacos', 'times_planned' => 2, 'ingredients' => ['Tortillas', 'Beef']],
            ],
        ]);
});
