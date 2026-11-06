<?php

use App\Mcp\Tools\MealPlanning\UpdateMealSlots;
use App\Models\MealPlanSlot;
use App\Models\MealTemplateItem;
use App\Models\User;
use Carbon\CarbonImmutable;
use Tests\Feature\Mcp\SunnyTestServer;

test('it renames, reorders, adds, and removes slots, keeping saved history', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-04 12:00', 'America/Chicago'));
    $user = User::factory()->create();
    $team = $user->currentTeam;
    $team->update(['meals_saved_through' => '2026-11-03']);
    [$breakfast, $lunch, $dinner] = $team->mealSlots()->get()->all();
    MealTemplateItem::factory()->for($lunch, 'slot')->create(['weekday' => 4]);
    $past = MealPlanSlot::factory()->for($lunch, 'slot')->create(['date' => '2026-11-03']);
    MealPlanSlot::factory()->for($lunch, 'slot')->create(['date' => '2026-11-05']);

    $response = SunnyTestServer::actingAs($user)->tool(UpdateMealSlots::class, ['slots' => [
        ['id' => $dinner->id, 'name' => 'Supper'],
        ['id' => $breakfast->id, 'name' => 'Breakfast'],
        ['name' => 'Snack'],
    ]]);

    $snack = $team->mealSlots()->firstWhere('name', 'Snack');

    $response->assertOk()->assertStructuredContent(['slots' => [
        ['id' => $dinner->id, 'name' => 'Supper'],
        ['id' => $breakfast->id, 'name' => 'Breakfast'],
        ['id' => $snack->id, 'name' => 'Snack'],
    ]]);

    expect($lunch->fresh())->toBeTrashed()
        ->and(MealTemplateItem::count())->toBe(0)
        ->and(MealPlanSlot::pluck('id')->all())->toBe([$past->id]);
});

test('it rejects another team\'s slot ids', function () {
    $user = User::factory()->create();
    $other = User::factory()->create()->currentTeam->mealSlots()->first();

    SunnyTestServer::actingAs($user)
        ->tool(UpdateMealSlots::class, ['slots' => [['id' => $other->id, 'name' => 'Mine now']]])
        ->assertHasErrors();

    expect($other->fresh()->name)->toBe('Breakfast')
        ->and($user->currentTeam->mealSlots()->count())->toBe(3);
});
