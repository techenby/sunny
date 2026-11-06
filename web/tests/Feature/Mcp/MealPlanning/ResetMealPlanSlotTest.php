<?php

use App\Mcp\Tools\MealPlanning\ResetMealPlanSlot;
use App\Models\MealPlanSlot;
use App\Models\MealTemplateItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Feature\Mcp\SunnyTestServer;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'America/Chicago'));

    $this->user = User::factory()->create();
    $this->user->currentTeam->update(['meals_saved_through' => '2026-11-03']);
    $this->dinner = $this->user->currentTeam->mealSlots()->firstWhere('name', 'Dinner');
    MealTemplateItem::factory()->for($this->dinner, 'slot')->create(['weekday' => 4, 'name' => 'Tacos']);
});

test('it puts an upcoming slot back on the template', function () {
    MealPlanSlot::factory()->for($this->dinner, 'slot')->create(['date' => '2026-11-05'])->items()->create(['name' => 'Pizza']);

    SunnyTestServer::actingAs($this->user)
        ->tool(ResetMealPlanSlot::class, ['date' => '2026-11-05', 'meal_slot_id' => $this->dinner->id])
        ->assertOk()
        ->assertStructuredContent([
            'date' => '2026-11-05',
            'meal_slot_id' => $this->dinner->id,
            'slot_name' => 'Dinner',
            'items' => [['recipe_id' => null, 'name' => 'Tacos']],
        ]);

    expect(MealPlanSlot::count())->toBe(0);
});

test('it will not reset a day saved to history', function () {
    MealPlanSlot::factory()->for($this->dinner, 'slot')->create(['date' => '2026-11-03']);

    SunnyTestServer::actingAs($this->user)
        ->tool(ResetMealPlanSlot::class, ['date' => '2026-11-03', 'meal_slot_id' => $this->dinner->id])
        ->assertHasErrors(['2026-11-03 has ended and is saved as history, so it cannot be reset to the template.']);

    expect(MealPlanSlot::count())->toBe(1);
});
