<?php

use App\Models\MealPlanSlot;
use App\Models\Recipe;
use App\Models\Team;
use Illuminate\Support\Facades\Date;

test('slug is auto-generated from name', function () {
    $recipe = Recipe::create([
        'team_id' => Team::factory()->create()->id,
        'name' => 'Chocolate Cake',
    ]);

    expect($recipe)->slug->toBe('chocolate-cake');
});

test('duplicate slugs append counter within same team', function () {
    $team = Team::factory()->create();

    $recipe1 = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe1)->slug->toBe('chocolate-cake');

    $recipe2 = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe2)->slug->toBe('chocolate-cake-1');

    $recipe3 = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe3)->slug->toBe('chocolate-cake-2');
});

test('same slug can exist in different teams', function () {
    $team1 = Team::factory()->create();
    $team2 = Team::factory()->create();

    $recipe1 = Recipe::create([
        'team_id' => $team1->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe1)->slug->toBe('chocolate-cake');

    $recipe2 = Recipe::create([
        'team_id' => $team2->id,
        'name' => 'Chocolate Cake',
    ]);
    expect($recipe2)->slug->toBe('chocolate-cake');
});

test('updating name generates new unique slug', function () {
    $team = Team::factory()->create();

    Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);

    $recipe2 = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Vanilla Cake',
    ]);
    expect($recipe2)->slug->toBe('vanilla-cake');

    $recipe2->update(['name' => 'Chocolate Cake']);
    expect($recipe2->fresh())->slug->toBe('chocolate-cake-1');
});

test('updating without changing name does not change slug', function () {
    $recipe = Recipe::create([
        'team_id' => Team::factory()->create()->id,
        'name' => 'Chocolate Cake',
    ]);

    $originalSlug = $recipe->slug;
    $recipe->update(['servings' => '8']);

    expect($recipe->fresh())->slug->toBe($originalSlug);
});

test('updating keeps existing slug if still unique', function () {
    $team = Team::factory()->create();

    $recipe = Recipe::create([
        'team_id' => $team->id,
        'name' => 'Chocolate Cake',
    ]);

    $recipe->update(['name' => 'Chocolate Cake Modified']);

    expect($recipe->fresh())->slug->toBe('chocolate-cake-modified');
});

test('source can be shortened', function () {
    $recipe = Recipe::factory()->make(['source' => 'https://www.italianfoodforever.com/2018/12/buttery-toasted-almond-crescent-cookies/?fbclid=IwAR35HddGDTbORlGEwro1CehIKuGDvGtwc44zMl05BLEjF4NuNF-hKDrpJPU']);

    expect($recipe->shortenedSource())->toBe('italianfoodforever.com');
});

test('enabling sharing generates a share token', function () {
    $recipe = Recipe::factory()->create();

    expect($recipe->isShared())->toBeFalse();

    $recipe->enableSharing();

    expect($recipe->isShared())->toBeTrue()
        ->and($recipe->share_token)->not->toBeNull();
});

test('disabling sharing removes the share token', function () {
    $recipe = Recipe::factory()->shared()->create();

    expect($recipe->isShared())->toBeTrue();

    $recipe->disableSharing();

    expect($recipe->isShared())->toBeFalse()
        ->and($recipe->share_token)->toBeNull();
});

test('ingredient lines come from list items, keeping nested items as their own lines', function () {
    $recipe = Recipe::factory()->make([
        'ingredients' => '<h3>Sauce</h3><ul><li>2 cups <strong>flour</strong></li><li>Spices<ul><li>1 tsp salt &amp; pepper</li></ul></li><li>  </li></ul>',
    ]);

    expect($recipe->ingredientLines())->toBe(['2 cups flour', 'Spices', '1 tsp salt & pepper']);
});

test('ingredient lines fall back to paragraphs and line breaks', function () {
    $recipe = Recipe::factory()->make(['ingredients' => '<p>1 egg</p><p>2 cups milk<br>Butter</p>']);

    expect($recipe->ingredientLines())->toBe(['1 egg', '2 cups milk', 'Butter']);
});

test('last made is the latest planned day before today where the team lives', function () {
    $this->travelTo(Date::parse('2026-11-10 03:00:00', 'UTC')); // Nov 9 in Chicago

    $team = Team::factory()->create(['timezone' => 'America/Chicago']);
    $recipe = Recipe::factory()->for($team)->create();
    $dinner = $team->mealSlots()->firstWhere('name', 'Dinner');

    expect($recipe->lastMadeOn())->toBeNull();

    foreach (['2026-11-03', '2026-11-08', '2026-11-09', '2026-11-12'] as $date) {
        MealPlanSlot::factory()->for($dinner, 'slot')->create(['date' => $date])
            ->items()->create(['recipe_id' => $recipe->id, 'name' => $recipe->name]);
    }

    expect($recipe->lastMadeOn()->toDateString())->toBe('2026-11-08');
});
